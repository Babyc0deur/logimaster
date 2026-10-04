<?php

namespace App\Domain\Fleet;

use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;

/** Statistiques d'un véhicule sur une période (onglet « Statistiques » de la fiche). */
class VehicleStats
{
    public function forPeriod(Vehicle $v, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

        $sorties = SortieVehicule::where('vehicle_id', $v->id)->whereNotNull('km_arrivee')
            ->where('statut', '!=', 'annulee')->whereDateBetween('date_sortie', $from, $to)->get(['km_depart', 'km_arrivee', 'date_sortie']);
        $km = (float) $sorties->sum(fn ($s) => $s->km_arrivee - $s->km_depart);
        $joursUtilises = $sorties->map(fn ($s) => $s->date_sortie->toDateString())->unique()->count();

        $fuel = Ravitaillement::where('vehicle_id', $v->id)->whereDateBetween('date_ravitaillement', $from, $to);
        $litres = (float) (clone $fuel)->sum('litres');
        $coutCarburant = (float) (clone $fuel)->sum(\DB::raw('litres * prix_unitaire'));

        $coutMaintenance = (float) Vidange::where('vehicle_id', $v->id)
            ->whereDateBetween('date', $from, $to)->sum('montant')
            + (float) Immobilisation::where('vehicle_id', $v->id)
                ->whereDateBetween('date_debut', $from, $to)->sum('montant');

        $joursImmobilises = 0;
        foreach (Immobilisation::where('vehicle_id', $v->id)->whereDate('date_debut', '<=', $to)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $from))->get() as $i) {
            $debut = $i->date_debut->toImmutable()->startOfDay()->max($from->startOfDay());
            $fin = ($i->date_fin?->toImmutable()->startOfDay() ?? $to->startOfDay())->min($to->startOfDay());
            $joursImmobilises += max(0, (int) $debut->diffInDays($fin) + 1);
        }
        $joursImmobilises = min($joursImmobilises, $days);

        $reelle = $km > 0 && $litres > 0 ? round($litres / $km * 100, 2) : null;

        return [
            'km' => $km,
            'nb_sorties' => $sorties->count(),
            'litres' => $litres,
            'consommation_reelle' => $reelle,
            'consommation_theorique' => $v->consommation_theorique !== null ? (float) $v->consommation_theorique : null,
            'taux_utilisation' => round($joursUtilises / $days * 100, 1),
            'taux_disponibilite' => round(($days - $joursImmobilises) / $days * 100, 1),
            'cout_carburant' => $coutCarburant,
            'cout_maintenance' => $coutMaintenance,
            'cout_total' => $coutCarburant + $coutMaintenance,
            'cout_km' => $km > 0 ? round(($coutCarburant + $coutMaintenance) / $km, 1) : null,
            'jours' => $days,
        ];
    }

    /** @return array<int, array{label: string, km: float, cout: float}> */
    public function monthly(Vehicle $v, int $months = 12): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $s = $this->forPeriod($v, $start, $start->endOfMonth());
            $out[] = ['label' => $start->translatedFormat('M y'), 'km' => $s['km'], 'cout' => $s['cout_total'], 'litres' => $s['litres']];
        }

        return $out;
    }
}
