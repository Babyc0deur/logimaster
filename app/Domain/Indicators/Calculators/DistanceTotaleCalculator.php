<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\SortieVehicule;
use Carbon\CarbonImmutable;

/** Km parcourus par les sorties clôturées de la période (par motif et par véhicule en détail). */
class DistanceTotaleCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'distance_totale';
    }

    public function isRatio(): bool
    {
        return false;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $rows = SortieVehicule::query()->with('vehicle:id,immatriculation')
            ->where('district_id', $districtId)
            ->whereNotNull('km_arrivee')
            ->whereDateBetween('date_sortie', $start, $end)
            ->get(['vehicle_id', 'motif', 'km_depart', 'km_arrivee']);

        $km = fn ($s) => $s->km_arrivee - $s->km_depart;
        $byMotif = $rows->groupBy('motif')->map(fn ($g) => (float) $g->sum($km));
        $byVehicle = $rows->groupBy(fn ($s) => $s->vehicle?->immatriculation ?? '—')->map(fn ($g) => (float) $g->sum($km))->sortDesc();

        return new IndicatorResult((float) $byMotif->sum(), [
            'par_motif' => $byMotif->all(),
            'par_vehicule' => $byVehicle->all(),
            'nb_sorties' => $rows->count(),
        ]);
    }
}
