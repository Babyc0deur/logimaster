<?php

namespace App\Domain\Indicators;

use App\Domain\Indicators\Calculators as C;
use App\Models\IndicatorSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class IndicatorService
{
    /** @return array<int, IndicatorCalculator> */
    public function calculators(): array
    {
        return [
            new C\DistanceTotaleCalculator,
            new C\RespectChronogrammeCalculator,
            new C\TauxImmobilisationCalculator,
            new C\UtilisationVehiculesCalculator,
            new C\CoutGlobalCalculator,
            new C\UtilisationRationnelleCarburantCalculator,
            new C\CarburantParMotifCalculator,
            new C\RespectCircuitsCalculator,
            new C\RespectEspcCalculator,
        ];
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(fn (IndicatorCalculator $c) => $c->key(), $this->calculators());
    }

    /** Recalcule et enregistre (upsert) les 9 indicateurs d'un district pour le mois de $month. */
    public function computeForDistrict(string $districtId, CarbonImmutable $month): int
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        self::withoutTenant(function () use ($districtId, $start, $end) {
            foreach ($this->calculators() as $calculator) {
                $result = $calculator->compute($districtId, $start, $end);
                IndicatorSnapshot::updateOrCreate(
                    ['district_id' => $districtId, 'indicator_key' => $calculator->key(), 'period' => $start->toDateString()],
                    ['value' => $result->value, 'breakdown' => $result->breakdown, 'computed_at' => now()],
                );
            }
        });

        return count($this->calculators());
    }

    /**
     * Calculs hors de la limitation automatique de Filament au district du menu (tenant) : depuis une page de
     * l'administration, les requêtes des calculateurs ne verraient sinon que ce district et compteraient zéro ailleurs.
     */
    public static function withoutTenant(callable $callback): mixed
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (! $tenant) {
            return $callback();
        }
        \Filament\Facades\Filament::setTenant(null, isQuiet: true);
        try {
            return $callback();
        } finally {
            \Filament\Facades\Filament::setTenant($tenant, isQuiet: true);
        }
    }

    /**
     * Indicateurs calculés à la volée sur une période quelconque (filtre « Du / Au » qui n'est pas un mois entier) :
     * même agrégation que summary() (ratios pondérés, sommes, détails fusionnés), avec la période précédente de même durée.
     *
     * @param  array<int, string>  $districtIds
     */
    public function summaryForRange(array $districtIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $now = $this->rangeRows($districtIds, $from, $to);
        $before = $this->rangeRows($districtIds, $from->subDays($days), $from->subDay()->endOfDay());

        return $this->withTrend($now, $before);
    }

    private function rangeRows(array $districtIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return self::withoutTenant(function () use ($districtIds, $from, $to) {
            $out = [];
            foreach ($this->calculators() as $calculator) {
                $results = array_map(fn ($id) => $calculator->compute($id, $from, $to), $districtIds);
                $breakdown = $this->mergeBreakdowns(array_map(fn ($r) => $r->breakdown, $results));
                if ($calculator->isRatio()) {
                    $num = (float) ($breakdown['numerator'] ?? 0);
                    $den = (float) ($breakdown['denominator'] ?? 0);
                    $value = $den > 0 ? round($num / $den * 100, 4) : 0.0;
                } else {
                    $value = round(array_sum(array_map(fn ($r) => $r->value, $results)), 4);
                }
                $out[$calculator->key()] = ['value' => $value, 'breakdown' => $breakdown, 'districts' => count($results),
                    'evaluated' => IndicatorCatalog::evaluated($calculator->key(), $breakdown)];
            }

            return $out;
        });
    }

    /**
     * Valeurs agrégées sur plusieurs districts pour un mois : sommes pour les indicateurs additifs,
     * ratio pondéré (Σ numerator / Σ denominator) pour les ratios. Les détails (par véhicule, écarts, non-livrés…)
     * sont fusionnés : nombres additionnés, listes concaténées.
     *
     * @param  array<int, string>|null  $districtIds  null = tous les districts
     * @return array<string, array{value: float, breakdown: array, districts: int}>
     */
    public function summary(?array $districtIds, CarbonImmutable $month): array
    {
        $snapshots = IndicatorSnapshot::query()
            ->whereDate('period', $month->startOfMonth()->toDateString())
            ->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->get()->groupBy('indicator_key');

        $out = [];
        foreach ($this->calculators() as $calculator) {
            /** @var Collection $group */
            $group = $snapshots->get($calculator->key(), collect());
            $breakdown = $this->mergeBreakdowns($group->pluck('breakdown')->filter()->all());

            if ($calculator->isRatio()) {
                $num = (float) ($breakdown['numerator'] ?? 0);
                $den = (float) ($breakdown['denominator'] ?? 0);
                $value = $den > 0 ? round($num / $den * 100, 4) : 0.0;
            } else {
                $value = round((float) $group->sum('value'), 4);
            }
            $out[$calculator->key()] = ['value' => $value, 'breakdown' => $breakdown, 'districts' => $group->count(), 'evaluated' => IndicatorCatalog::evaluated($calculator->key(), $breakdown)];
        }

        return $out;
    }

    /**
     * Résumé du mois avec la valeur du mois précédent et l'évolution.
     *
     * @return array<string, array{value: float, breakdown: array, districts: int, previous: ?float, delta: ?float, delta_pct: ?float}>
     */
    public function summaryWithTrend(?array $districtIds, CarbonImmutable $month): array
    {
        return $this->withTrend($this->summary($districtIds, $month), $this->summary($districtIds, $month->subMonthNoOverflow()));
    }

    /** Ajoute à chaque indicateur la valeur de la période précédente et l'évolution. */
    private function withTrend(array $now, array $before): array
    {
        foreach ($now as $key => &$row) {
            $had = ($before[$key]['districts'] ?? 0) > 0 && ($before[$key]['evaluated'] ?? true);
            $prev = $had ? $before[$key]['value'] : null;
            $row['previous'] = $prev;
            $comparable = $prev !== null && $row['evaluated'];
            $row['delta'] = $comparable ? round($row['value'] - $prev, 2) : null;
            $row['delta_pct'] = ($comparable && $prev != 0) ? round(($row['value'] - $prev) / abs($prev) * 100, 1) : null;
        }

        return $now;
    }

    /** @return array<int, array{period: string, value: float}> */
    /** @param  CarbonImmutable|null  $anchor  dernier mois affiché (défaut : mois courant) */
    public function history(?array $districtIds, string $key, int $months, ?CarbonImmutable $anchor = null): array
    {
        $calculator = collect($this->calculators())->first(fn ($c) => $c->key() === $key);
        $last = ($anchor ?? CarbonImmutable::now())->startOfMonth();
        $from = $last->subMonths($months - 1);

        $rows = IndicatorSnapshot::where('indicator_key', $key)
            ->whereDate('period', '>=', $from->toDateString())
            ->when($anchor !== null, fn ($q) => $q->whereDate('period', '<=', $last->toDateString()))
            ->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->orderBy('period')->get()->groupBy(fn ($s) => $s->period->format('Y-m'));

        return $rows->map(function ($g, $period) use ($calculator) {
            if (! $calculator->isRatio()) {
                return ['period' => $period, 'value' => round((float) $g->sum('value'), 4)];
            }
            $b = $this->mergeBreakdowns($g->pluck('breakdown')->filter()->all());
            $den = (float) ($b['denominator'] ?? 0);
            if (! IndicatorCatalog::evaluated($calculator->key(), $b)) {
                return ['period' => $period, 'value' => null];   // non évalué : pas de point sur la courbe
            }

            return ['period' => $period, 'value' => $den > 0 ? round((float) ($b['numerator'] ?? 0) / $den * 100, 4) : 0.0];
        })->values()->all();
    }

    /**
     * Fusionne les détails de plusieurs districts : nombres additionnés, listes (d'objets) concaténées,
     * tableaux associatifs fusionnés récursivement, autres valeurs : première rencontrée.
     *
     * @param  array<int, array<string, mixed>>  $breakdowns
     */
    public function mergeBreakdowns(array $breakdowns): array
    {
        $out = [];
        foreach ($breakdowns as $b) {
            $out = $this->merge($out, $b);
        }

        return $out;
    }

    private function merge(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (! array_key_exists($key, $a)) {
                $a[$key] = $value;

                continue;
            }
            if (is_array($value) && is_array($a[$key])) {
                $a[$key] = (array_is_list($value) && array_is_list($a[$key]) && (($value[0] ?? null) === null || is_array($value[0])))
                    ? array_merge($a[$key], $value)
                    : $this->merge($a[$key], $value);
            } elseif (is_numeric($value) && is_numeric($a[$key])) {
                $a[$key] += $value;
            }
        }

        return $a;
    }
}
