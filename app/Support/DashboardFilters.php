<?php

namespace App\Support;

use App\Models\District;
use App\Models\Region;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Résout les filtres du dashboard (PRES > Région > District + période) en une liste de districts
 * et un intervalle de dates, toujours restreints au périmètre de l'utilisateur connecté.
 */
class DashboardFilters
{
    /** Districts que l'utilisateur connecté peut consulter. */
    public static function accessibleDistricts(): Builder
    {
        $ids = auth()->user()?->accessibleDistrictIds();
        $query = District::query();

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    /**
     * @param  array<string, mixed>|null  $filters  null = aucun filtre touché : on reste sur le district courant.
     * @return array<int, string>
     */
    public static function districtIds(?array $filters): array
    {
        $query = self::accessibleDistricts();

        if ($filters === null) {
            $tenant = Filament::getTenant();

            return $tenant ? [$tenant->getKey()] : $query->pluck('id')->all();
        }

        if (! empty($filters['district_id'])) {
            $query->whereKey($filters['district_id']);
        } elseif (! empty($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        } elseif (! empty($filters['pres_id'])) {
            $query->whereIn('region_id', Region::where('pres_id', $filters['pres_id'])->select('id'));
        }

        return $query->pluck('id')->all();
    }

    /** Début de la période par défaut (config « logimaster.default_period.from »), sinon le 1er du mois courant. */
    public static function defaultFrom(): CarbonImmutable
    {
        $from = config('logimaster.default_period.from');

        return $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::now()->startOfMonth();
    }

    /** Fin de la période par défaut, sinon aujourd'hui. */
    public static function defaultUntil(): CarbonImmutable
    {
        $until = config('logimaster.default_period.until');

        return $until ? CarbonImmutable::parse($until)->endOfDay() : CarbonImmutable::now()->endOfDay();
    }

    /** Début du filtre « Période » des listes : la période par défaut configurée, sinon aucun filtre (null). */
    public static function filterFrom(): ?string
    {
        return config('logimaster.default_period.from') ?: null;
    }

    /** Fin du filtre « Période » des listes (null si aucune période n'est configurée). */
    public static function filterUntil(): ?string
    {
        return config('logimaster.default_period.until') ?: null;
    }

    /** Mois proposé par défaut (Y-m) dans les sélecteurs : mois de la fin de période, sinon mois courant. */
    public static function defaultMonthKey(): string
    {
        return config('logimaster.default_period.until') ? self::defaultUntil()->format('Y-m') : CarbonImmutable::now()->format('Y-m');
    }

    /**
     * Mois des sélecteurs, du plus récent au plus ancien : ceux de la période par défaut si elle est définie,
     * sinon les $fallback derniers mois.
     *
     * @return array<string, string> Y-m => libellé
     */
    public static function monthOptions(int $fallback = 12): array
    {
        $options = [];
        if (config('logimaster.default_period.until')) {
            $from = (config('logimaster.default_period.from') ? self::defaultFrom() : self::defaultUntil()->subMonths($fallback - 1))->startOfMonth();
            for ($m = self::defaultUntil()->startOfMonth(); $m >= $from; $m = $m->subMonth()) {
                $options[$m->format('Y-m')] = ucfirst($m->translatedFormat('F Y'));
            }

            return $options;
        }
        for ($i = 0; $i < $fallback; $i++) {
            $m = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $options[$m->format('Y-m')] = ucfirst($m->translatedFormat('F Y'));
        }

        return $options;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function period(?array $filters): array
    {
        $from = ! empty($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'])->startOfDay() : self::defaultFrom();
        $until = ! empty($filters['date_until']) ? CarbonImmutable::parse($filters['date_until'])->endOfDay() : self::defaultUntil();

        return [$from, $until];
    }

    /** Mois sélectionné (filtre « periode » au format Y-m, défaut : mois courant). @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function month(?array $filters): array
    {
        $month = ! empty($filters['periode'])
            ? CarbonImmutable::createFromFormat('Y-m', $filters['periode'])->startOfMonth()
            : CarbonImmutable::createFromFormat('Y-m', self::defaultMonthKey())->startOfMonth();

        return [$month, $month->endOfMonth()];
    }

    /** Mois des indicateurs : filtre « periode » (page Indicateurs), sinon mois de la date de fin du dashboard, sinon mois courant. */
    public static function indicatorMonth(?array $filters): CarbonImmutable
    {
        if (! empty($filters['periode'])) {
            return CarbonImmutable::createFromFormat('Y-m', $filters['periode'])->startOfMonth();
        }
        if (! empty($filters['date_until'])) {
            return CarbonImmutable::parse($filters['date_until'])->startOfMonth();
        }

        return CarbonImmutable::createFromFormat('Y-m', self::defaultMonthKey())->startOfMonth();
    }

    public static function scopeLabel(?array $filters): string
    {
        $count = count(self::districtIds($filters));

        return $count === 1
            ? (District::find(self::districtIds($filters)[0])?->name ?? '1 district')
            : "{$count} districts";
    }
}
