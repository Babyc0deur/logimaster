<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\ImmobilisationDays;
use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Jours d'utilisation / jours de disponibilité des véhicules (jours du mois moins jours d'immobilisation).
 * Un véhicule est « utilisé » un jour s'il a au moins une sortie non annulée ce jour-là.
 */
class UtilisationVehiculesCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'utilisation_vehicules';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $days = (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        $vehicles = Vehicle::where('district_id', $districtId)->orderBy('immatriculation')->get(['id', 'immatriculation']);
        $imm = ImmobilisationDays::compute($districtId, $start, $end)['per_vehicle'];

        $used = SortieVehicule::where('district_id', $districtId)->where('statut', '!=', 'annulee')
            ->whereDateBetween('date_sortie', $start, $end)->get(['vehicle_id', 'date_sortie'])
            ->groupBy('vehicle_id')->map(fn ($g) => $g->map(fn ($s) => $s->date_sortie->toDateString())->unique()->count());

        $perVehicle = [];
        $usedTotal = 0;
        $availableTotal = 0;
        foreach ($vehicles as $v) {
            $available = max(0, $days - ($imm[$v->id] ?? 0));
            $u = min((int) ($used[$v->id] ?? 0), max($available, (int) ($used[$v->id] ?? 0)));
            $perVehicle[$v->immatriculation] = ['jours_utilises' => $u, 'jours_disponibles' => $available];
            $usedTotal += $u;
            $availableTotal += $available;
        }

        return IndicatorResult::ratio($usedTotal, $availableTotal, ['par_vehicule' => $perVehicle, 'nb_vehicules' => $vehicles->count()]);
    }
}
