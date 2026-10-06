<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Fleet\FieldReports;
use App\Domain\Mobile\WebPushSender;
use App\Models\Chronogramme;
use App\Models\Immobilisation;
use App\Models\Personnel;
use App\Models\Signalement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Application convoyeur — le district en lecture (planning des sorties validées, véhicules : disponibilité, vidange,
 * échéances) et les signalements terrain (vidange faite, panne), plus la notification de test.
 */
class FleetController extends MobileController
{
    private function personnel(Request $request): Personnel
    {
        return Personnel::findOrFail($request->user()->personnel_id);
    }

    // ------------------------------------------------------------------ planning du district

    /** Sorties validées du district sur une période (7 jours par défaut, 31 au plus), en lecture seule. */
    public function planning(Request $request)
    {
        $data = $request->validate(['debut' => ['nullable', 'date'], 'jours' => ['nullable', 'integer', 'min:1', 'max:31']]);
        $me = $this->personnel($request);
        $from = isset($data['debut']) ? CarbonImmutable::parse($data['debut'])->startOfDay() : CarbonImmutable::today()->startOfWeek();
        $to = $from->addDays(($data['jours'] ?? 7) - 1);

        $plans = Chronogramme::with(['circuit:id,nom', 'vehicle:id,immatriculation', 'driver:id,nom_complet', 'personnels:id,nom_complet,fonction', 'sortie:id,statut'])
            ->withCount('livraisons')
            ->where('district_id', $me->district_id)->where('validation_statut', 'valide')->where('statut', '!=', 'annulee')
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
                'moi' => $p->personnels->contains('id', $me->id) || ($me->driver_id && $p->driver_id === $me->driver_id),
            ])->values(),
        ];
    }

    // ------------------------------------------------------------------ véhicules du district

    /** Véhicules du district : disponibilité, vidange, échéances, immobilisation en cours, signalements en attente. */
    public function vehicules(Request $request)
    {
        $me = $this->personnel($request);
        $today = CarbonImmutable::today();
        $vehicles = Vehicle::where('district_id', $me->district_id)->orderBy('immatriculation')->get();
        $ids = $vehicles->pluck('id');
        $planned = Chronogramme::whereIn('vehicle_id', $ids)->whereDate('date_prevue', $today)->where('validation_statut', 'valide')->where('statut', '!=', 'annulee')->pluck('vehicle_id')->all();
        $running = SortieVehicule::whereIn('vehicle_id', $ids)->where('statut', 'en_cours')->pluck('vehicle_id')->all();
        $lastOil = Vidange::whereIn('vehicle_id', $ids)->orderByDesc('date')->get()->unique('vehicle_id')->keyBy('vehicle_id');
        $immos = Immobilisation::whereIn('vehicle_id', $ids)->where('statut', '!=', 'terminee')->orderByDesc('date_debut')->get()->unique('vehicle_id')->keyBy('vehicle_id');
        $pending = Signalement::whereIn('vehicle_id', $ids)->where('statut', 'nouveau')->selectRaw('vehicle_id, count(*) as n')->groupBy('vehicle_id')->pluck('n', 'vehicle_id');
        $days = fn ($date) => $date ? (int) $today->diffInDays(CarbonImmutable::parse($date), false) : null;

        return ['data' => $vehicles->map(function (Vehicle $v) use ($planned, $running, $lastOil, $immos, $pending, $days) {
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
                'signalements_en_attente' => (int) ($pending[$v->id] ?? 0),
            ];
        })->values()];
    }

    // ------------------------------------------------------------------ signalements terrain

    /** Vidange faite ou panne, depuis le téléphone (rejouable : `client_ref`). Le bureau valide ensuite. */
    public function signalement(Request $request, string $id): JsonResponse
    {
        $me = $this->personnel($request);
        $vehicle = Vehicle::where('district_id', $me->district_id)->findOrFail($id);
        $data = $request->validate([
            'client_ref' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::in(array_keys(Signalement::TYPES))],
            'km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type_vidange' => ['nullable', Rule::in(['simple', 'complete', 'revision'])],
            'montant' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'prestataire' => ['nullable', 'string', 'max:120'],
            'motif' => ['nullable', Rule::in(array_keys(Immobilisation::MOTIFS))],
            'done_at' => ['nullable', 'date', 'before_or_equal:now +1 day'],
            'photo' => ['nullable', 'string', 'max:6000000'],
        ]);
        abort_if($data['type'] === 'panne' && blank($data['description'] ?? null), 422, 'Décrivez la panne.');
        $photo = isset($data['photo']) ? $this->decodePhoto($data['photo']) : null;

        if ($existing = Signalement::where('client_ref', $data['client_ref'])->first()) {   // envoi rejoué
            abort_unless($existing->vehicle_id === $vehicle->id, 409, 'Référence déjà utilisée.');
            if ($photo && ! $existing->photo) {
                $existing->update(['photo' => $this->storePhoto($photo, 'signalements')]);
            }

            return response()->json(['id' => $existing->id, 'statut' => $existing->statut], 200);
        }

        $s = Signalement::create([
            'district_id' => $vehicle->district_id, 'vehicle_id' => $vehicle->id, 'personnel_id' => $me->id, 'user_id' => $request->user()->getKey(),
            'type' => $data['type'], 'km' => $data['km'] ?? null, 'description' => $data['description'] ?? null,
            'details' => array_filter(['type_vidange' => $data['type_vidange'] ?? null, 'montant' => $data['montant'] ?? null, 'prestataire' => $data['prestataire'] ?? null, 'motif' => $data['motif'] ?? null], fn ($v) => $v !== null),
            'photo' => $photo ? $this->storePhoto($photo, 'signalements') : null,
            'client_ref' => $data['client_ref'], 'signale_at' => isset($data['done_at']) ? CarbonImmutable::parse($data['done_at']) : now(),
        ]);
        app(FieldReports::class)->notifyOffice($s);

        return response()->json(['id' => $s->id, 'statut' => $s->statut], 201);
    }

    // ------------------------------------------------------------------ notification de test

    /** Envoie une notification de test aux téléphones de l'utilisateur et explique ce qui bloque si rien ne part. */
    public function pushTest(Request $request, WebPushSender $sender)
    {
        $subs = $request->user()->pushSubscriptions()->get();
        if (! $sender->configured()) {
            return ['ok' => false, 'raison' => 'serveur', 'message' => 'Les notifications ne sont pas configurées sur le serveur (clés VAPID). Prévenez l\'administrateur.'];
        }
        if ($subs->isEmpty()) {
            return ['ok' => false, 'raison' => 'appareil', 'message' => 'Ce téléphone n\'est pas abonné : touchez « Activer » dans Notifications, puis acceptez.'];
        }
        $sent = 0;
        foreach ($subs as $sub) {
            $sent += $sender->send($sub, ['title' => 'Notification de test', 'body' => 'Les notifications LogiMaster fonctionnent sur ce téléphone.', 'url' => '/m', 'tag' => 'test']) ? 1 : 0;
        }

        return $sent
            ? ['ok' => true, 'envoyees' => $sent, 'message' => 'Notification envoyée : elle doit apparaître dans quelques secondes.']
            : ['ok' => false, 'raison' => 'envoi', 'message' => 'Le service de notification a refusé l\'envoi. Désactivez puis réactivez les notifications, et réessayez.'];
    }
}
