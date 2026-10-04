<?php

namespace App\Domain\Indicators;

final class IndicatorResult
{
    /**
     * @param  array<string, mixed>  $breakdown  Pour les ratios : numerator / denominator (agrégation pondérée).
     */
    public function __construct(
        public readonly float $value,
        public readonly array $breakdown = [],
    ) {}

    public static function ratio(float $numerator, float $denominator, array $extra = []): self
    {
        $value = $denominator > 0 ? round($numerator / $denominator * 100, 4) : 0.0;

        return new self($value, ['numerator' => $numerator, 'denominator' => $denominator] + $extra);
    }
}
