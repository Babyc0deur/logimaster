<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\SortieVehicule;
use Carbon\CarbonImmutable;

/**
 * Sorties sur circuit dont le circuit a été respecté / sorties sur circuit évaluées.
 * Détail : écarts constatés (circuit, date, raison saisie en commentaire, km supplémentaires vs distance prévue).
 */
class RespectCircuitsCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'respect_circuits';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $sorties = SortieVehicule::with(['circuit:id,nom,distance_totale', 'vehicle:id,immatriculation'])
            ->where('district_id', $districtId)
            ->whereNotNull('circuit_id')->whereNotNull('circuit_respecte')->where('statut', '!=', 'annulee')
            ->whereDateBetween('date_sortie', $start, $end)->orderBy('date_sortie')->get();

        $ecarts = [];
        $extraKm = 0.0;
        foreach ($sorties->where('circuit_respecte', false) as $s) {
            $prevu = $s->circuit?->distance_totale !== null ? (float) $s->circuit->distance_totale : null;
            $extra = ($s->distance !== null && $prevu !== null) ? max(0, $s->distance - $prevu) : null;
            $extraKm += $extra ?? 0;
            $ecarts[] = [
                'circuit' => $s->circuit?->nom, 'date' => $s->date_sortie->toDateString(), 'vehicule' => $s->vehicle?->immatriculation,
                'raison' => $s->commentaires, 'km_supplementaires' => $extra,
            ];
        }

        return IndicatorResult::ratio($sorties->where('circuit_respecte', true)->count(), $sorties->count(), [
            'ecarts' => $ecarts, 'km_supplementaires' => round($extraKm, 1),
        ]);
    }
}
