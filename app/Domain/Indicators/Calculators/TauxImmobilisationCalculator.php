<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\ImmobilisationDays;
use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/** Jours-véhicule immobilisés / jours-véhicule de la période (détail par véhicule et par cause). */
class TauxImmobilisationCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'taux_immobilisation';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $vehicles = Vehicle::where('district_id', $districtId)->orderBy('immatriculation')->get(['id', 'immatriculation']);
        $imm = ImmobilisationDays::compute($districtId, $start, $end);

        $perVehicle = [];
        foreach ($vehicles as $v) {
            $perVehicle[$v->immatriculation] = ['jours' => $imm['per_vehicle'][$v->id] ?? 0, 'jours_total' => $days];
        }

        return IndicatorResult::ratio(array_sum($imm['per_vehicle']), $vehicles->count() * $days, [
            'jours_par_motif' => $imm['per_motif'],
            'par_vehicule' => $perVehicle,
            'nb_vehicules' => $vehicles->count(),
        ]);
    }
}
