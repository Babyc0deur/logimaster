<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Carburant théorique (km × consommation théorique / 100) rapporté au carburant réellement acheté.
 * 100 % = consommation conforme ; < 100 % = surconsommation. Détail par véhicule.
 */
class UtilisationRationnelleCarburantCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'utilisation_rationnelle_carburant';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $vehicles = Vehicle::where('district_id', $districtId)->whereNotNull('consommation_theorique')->get(['id', 'immatriculation', 'consommation_theorique']);
        $conso = $vehicles->pluck('consommation_theorique', 'id');

        $theorique = [];
        SortieVehicule::where('district_id', $districtId)->whereNotNull('km_arrivee')
            ->whereIn('vehicle_id', $conso->keys())->whereDateBetween('date_sortie', $start, $end)
            ->get(['vehicle_id', 'km_depart', 'km_arrivee'])
            ->each(function ($s) use ($conso, &$theorique) {
                $theorique[$s->vehicle_id] = ($theorique[$s->vehicle_id] ?? 0) + ($s->km_arrivee - $s->km_depart) * (float) $conso[$s->vehicle_id] / 100;
            });

        $reel = Ravitaillement::where('district_id', $districtId)
            ->whereIn('vehicle_id', $conso->keys())->whereDateBetween('date_ravitaillement', $start, $end)
            ->selectRaw('vehicle_id, SUM(litres) as litres')->groupBy('vehicle_id')->pluck('litres', 'vehicle_id');

        $perVehicle = [];
        foreach ($vehicles as $v) {
            $t = round($theorique[$v->id] ?? 0, 2);
            $r = round((float) ($reel[$v->id] ?? 0), 2);
            if ($t > 0 || $r > 0) {
                $perVehicle[$v->immatriculation] = ['theorique' => $t, 'reel' => $r];
            }
        }

        // véhicules actifs sur la période mais sans consommation théorique : non pris en compte (signalés)
        $active = SortieVehicule::where('district_id', $districtId)->whereDateBetween('date_sortie', $start, $end)->distinct()->pluck('vehicle_id')
            ->merge(Ravitaillement::where('district_id', $districtId)->whereDateBetween('date_ravitaillement', $start, $end)->distinct()->pluck('vehicle_id'))->unique();
        $sans = Vehicle::where('district_id', $districtId)->whereNull('consommation_theorique')->whereIn('id', $active)->orderBy('immatriculation')->pluck('immatriculation')->map(fn ($i) => ['immatriculation' => $i])->all();   // liste d'objets : fusionnée entre districts

        return IndicatorResult::ratio(round(array_sum($theorique), 2), (float) $reel->sum(), ['unite' => 'litres', 'par_vehicule' => $perVehicle, 'sans_consommation' => $sans]);
    }
}
