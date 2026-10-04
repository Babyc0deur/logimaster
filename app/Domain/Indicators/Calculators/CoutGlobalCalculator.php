<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Expense;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Coût global = carburant + maintenance (vidanges + immobilisations) + autres frais.
 * Les dépenses de type carburant/maintenance sont exclues pour éviter le double comptage.
 * Détail : répartition par poste, coût au km, coût par véhicule.
 */
class CoutGlobalCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'cout_global';
    }

    public function isRatio(): bool
    {
        return false;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $fuel = Ravitaillement::with('vehicle:id,immatriculation')->where('district_id', $districtId)
            ->whereDateBetween('date_ravitaillement', $start, $end)->get(['vehicle_id', 'litres', 'prix_unitaire']);
        $oil = Vidange::with('vehicle:id,immatriculation')->where('district_id', $districtId)
            ->whereDateBetween('date', $start, $end)->get(['vehicle_id', 'montant']);
        $immo = Immobilisation::with('vehicle:id,immatriculation')->where('district_id', $districtId)
            ->whereDateBetween('date_debut', $start, $end)->get(['vehicle_id', 'montant']);
        $others = Expense::with('vehicle:id,immatriculation')->where('district_id', $districtId)
            ->whereNotIn('type', ['carburant', 'maintenance'])->whereDateBetween('date_depense', $start, $end)->get(['vehicle_id', 'montant']);

        $carburant = (float) $fuel->sum(fn ($r) => $r->litres * $r->prix_unitaire);
        $vidanges = (float) $oil->sum('montant');
        $immobilisations = (float) $immo->sum('montant');
        $autres = (float) $others->sum('montant');
        $total = $carburant + $vidanges + $immobilisations + $autres;

        $perVehicle = [];
        $add = function ($rows, callable $amount) use (&$perVehicle) {
            foreach ($rows as $r) {
                $k = $r->vehicle?->immatriculation ?? 'Non affecté';
                $perVehicle[$k] = ($perVehicle[$k] ?? 0) + $amount($r);
            }
        };
        $add($fuel, fn ($r) => $r->litres * $r->prix_unitaire);
        $add($oil, fn ($r) => (float) $r->montant);
        $add($immo, fn ($r) => (float) $r->montant);
        $add($others, fn ($r) => (float) $r->montant);
        arsort($perVehicle);

        $km = (float) SortieVehicule::where('district_id', $districtId)
            ->whereNotNull('km_arrivee')->whereDateBetween('date_sortie', $start, $end)
            ->sum(DB::raw('km_arrivee - km_depart'));

        return new IndicatorResult($total, [
            'carburant' => $carburant,
            'maintenance' => $vidanges + $immobilisations,
            'vidanges' => $vidanges,
            'immobilisations' => $immobilisations,
            'autres_depenses' => $autres,
            'km' => $km,
            'par_vehicule' => array_map(fn ($v) => round($v), $perVehicle),
        ]);
    }
}
