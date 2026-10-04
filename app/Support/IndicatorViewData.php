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

        return self::$memo[$memoKey] ??= [
            'month' => $month, 'ids' => $ids,
            'rows' => app(IndicatorService::class)->summaryWithTrend($ids, $month),
        ];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
