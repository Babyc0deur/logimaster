<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Chronogramme;
use App\Models\Immobilisation;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Application mobile du bureau — le district en lecture : planning des sorties validées, véhicules (disponibilité,
 * vidange, échéances). Le district est celui que l'utilisateur a choisi dans l'application (`district_id`).
 */
class FleetController extends MobileController
{
    // ------------------------------------------------------------------ planning du district

    /** Sorties validées du district sur une période (7 jours par défaut, 31 au plus), en lecture seule. */
    public function planning(Request $request)
    {
        $data = $request->validate(['debut' => ['nullable', 'date'], 'jours' => ['nullable', 'integer', 'min:1', 'max:31']]);
        $from = isset($data['debut']) ? CarbonImmutable::parse($data['debut'])->startOfDay() : CarbonImmutable::today()->startOfWeek();
        $to = $from->addDays(($data['jours'] ?? 7) - 1);

        $plans = Chronogramme::with(['circuit:id,nom', 'vehicle:id,immatriculation', 'driver:id,nom_complet', 'personnels:id,nom_complet,fonction', 'sortie:id,statut'])
            ->withCount('livraisons')
            ->where('district_id', $this->districtId($request))->where('validation_statut', 'valide')->where('statut', '!=', 'annulee')
            ->whereBetween('date_prevue', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date_prevue')->orderBy('heure_depart')->get();

        return [
            'debut' => $from->toDateString(), 'fin' => $to->toDateString(),
            'data' => $plans->map(fn (Chronogramme $p) => [
                'id' => $p->id, 'date' => $p->date_prevue->toDateString(), 'heure' => $p->heure_depart ? substr($p->heure_depart, 0, 5) : null,
                'circuit' => $p->circuit?->nom ?? ($p->destination ?: 'Sortie'), 'vehicule' => $p->vehicle?->immatriculation,
                'chauffeur' => $p->driver?->nom_complet, 'chef' => $p->personnels->firstWhere('fonction', 'chef_mission')?->nom_complet,
                'equipe' => $p->personnels->count(), 'sites' => $p->livraisons_count,
                'etat' => match (true) { $p->sortie?->statut === 'en_cours' => 'en_cours', $p->sortie !== null => 'terminee', default => 'prevue' },
            ])->values(),
        ];
    }

    // ------------------------------------------------------------------ véhicules du district

    /** Véhicules du district : disponibilité, vidange, échéances, immobilisation en cours. */
    public function vehicules(Request $request)
    {
        $today = CarbonImmutable::today();
        $vehicles = Vehicle::where('district_id', $this->districtId($request))->orderBy('immatriculation')->get();
        $ids = $vehicles->pluck('id');
        $planned = Chronogramme::whereIn('vehicle_id', $ids)->whereDate('date_prevue', $today)->where('validation_statut', 'valide')->where('statut', '!=', 'annulee')->pluck('vehicle_id')->all();
        $running = SortieVehicule::whereIn('vehicle_id', $ids)->where('statut', 'en_cours')->pluck('vehicle_id')->all();
        $lastOil = Vidange::whereIn('vehicle_id', $ids)->orderByDesc('date')->get()->unique('vehicle_id')->keyBy('vehicle_id');
        $immos = Immobilisation::whereIn('vehicle_id', $ids)->where('statut', '!=', 'terminee')->orderByDesc('date_debut')->get()->unique('vehicle_id')->keyBy('vehicle_id');
        $days = fn ($date) => $date ? (int) $today->diffInDays(CarbonImmutable::parse($date), false) : null;

        return ['data' => $vehicles->map(function (Vehicle $v) use ($planned, $running, $lastOil, $immos, $days) {
            $immo = $immos->get($v->id);
            $dispo = match (true) {
                $immo !== null || $v->statut === 'en_maintenance' => 'immobilise',
                $v->statut === 'hors_service' => 'hors_service',
                in_array($v->id, $running, true) => 'en_mission',
                in_array($v->id, $planned, true) => 'reserve',
                default => 'disponible',
            };
            $oil = $lastOil->get($v->id);

            return [
                'id' => $v->id, 'immatriculation' => $v->immatriculation, 'modele' => trim($v->marque.' '.$v->modele) ?: null, 'carburant' => $v->type_carburant,
                'disponibilite' => $dispo, 'km_actuel' => $v->km_actuel !== null ? (int) $v->km_actuel : null,
                'vidange_km' => $v->km_vidange !== null ? (int) $v->km_vidange : null,
                'vidange_reste_km' => $v->km_vidange !== null && $v->km_actuel !== null ? (int) ($v->km_vidange - $v->km_actuel) : null,
                'derniere_vidange' => $oil ? ['date' => $oil->date?->toDateString(), 'km' => $oil->km] : null,
                'ct' => $v->date_ct?->toDateString(), 'ct_jours' => $days($v->date_ct),
                'assurance' => $v->date_assurance?->toDateString(), 'assurance_jours' => $days($v->date_assurance),
                'immobilisation' => $immo ? ['motif' => Immobilisation::MOTIFS[$immo->motif] ?? $immo->motif, 'depuis' => $immo->date_debut?->toDateString()] : null,
            ];
        })->values()];
    }
}
