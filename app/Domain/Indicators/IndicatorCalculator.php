<?php

namespace App\Domain\Indicators;

use Carbon\CarbonImmutable;

interface IndicatorCalculator
{
    /** Clé stockée dans indicator_snapshots.indicator_key. */
    public function key(): string;

    /** Vrai si l'indicateur est un ratio (agrégé par numerator/denominator), faux s'il est additif. */
    public function isRatio(): bool;

    /** Calcule l'indicateur pour un district sur [$start, $end] (bornes incluses). */
    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult;
}
