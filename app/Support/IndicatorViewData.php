<?php

namespace App\Support;

use App\Domain\Indicators\IndicatorService;

/** Résumé des indicateurs pour les widgets d'une même page (calculé une seule fois par requête et par jeu de filtres). */
class IndicatorViewData
{
    /** @var array<string, array> */
    private static array $memo = [];

    /**
     * @param  array<string, mixed>|null  $filters  filtres de page (scope + periode / date_until)
     * @return array{month: \Carbon\CarbonImmutable, ids: array<int, string>, rows: array<string, array>}
     */
    public static function get(?array $filters): array
    {
        $ids = DashboardFilters::districtIds($filters);
        $month = DashboardFilters::indicatorMonth($filters);
        $memoKey = md5(json_encode([$ids, $month->format('Y-m')]));

        if (! isset(self::$memo[$memoKey])) {
            self::computeMissing($ids, $month);
        }

        return self::$memo[$memoKey] ??= [
            'month' => $month, 'ids' => $ids,
            'rows' => app(IndicatorService::class)->summaryWithTrend($ids, $month),
        ];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * Mois jamais calculé pour certains districts (nouveau district, mois non encore passé par le calcul de nuit) :
     * ses indicateurs sont calculés tout de suite, au plus 15 districts par affichage pour garder la page rapide.
     * Les mois déjà calculés sont tenus à jour chaque nuit (01:00) et après chaque import.
     */
    private static function computeMissing(array $ids, \Carbon\CarbonImmutable $month): void
    {
        if ($ids === [] || $month->greaterThan(\Carbon\CarbonImmutable::now()->endOfMonth())) {
            return;
        }
        $have = \App\Models\IndicatorSnapshot::whereDate('period', $month->startOfMonth()->toDateString())->whereIn('district_id', $ids)->distinct()->pluck('district_id')->all();
        $service = app(IndicatorService::class);
        foreach (array_slice(array_values(array_diff($ids, $have)), 0, 15) as $id) {
            $service->computeForDistrict($id, $month);
        }
    }
}
