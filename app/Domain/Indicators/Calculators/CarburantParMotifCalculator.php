<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Ravitaillement;
use Carbon\CarbonImmutable;

/** Litres consommés, ventilés par motif de la sortie rattachée ("non_affecte" sinon). */
class CarburantParMotifCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'carburant_par_motif';
    }

    public function isRatio(): bool
    {
        return false;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $rows = Ravitaillement::with('sortie:id,motif')
            ->where('district_id', $districtId)
            ->whereDateBetween('date_ravitaillement', $start, $end)
            ->get(['id', 'sortie_id', 'litres', 'motif']);

        $byMotif = $rows->groupBy(fn ($r) => $r->motif ?? $r->sortie?->motif ?? 'non_affecte')
            ->map(fn ($g) => round((float) $g->sum('litres'), 2));

        return new IndicatorResult((float) $byMotif->sum(), ['par_motif' => $byMotif->all(), 'unite' => 'litres']);
    }
}
