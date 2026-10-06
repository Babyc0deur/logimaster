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

    /** Début de la période du tableau de bord à l'arrivée (« logimaster.dashboard_period »), sinon la période par défaut. */
    public static function dashboardFrom(): CarbonImmutable
    {
        $from = config('logimaster.dashboard_period.from');

        return $from ? CarbonImmutable::parse($from)->startOfDay() : self::defaultFrom();
    }

    public static function dashboardUntil(): CarbonImmutable
    {
        $until = config('logimaster.dashboard_period.until');

        return $until ? CarbonImmutable::parse($until)->endOfDay() : self::defaultUntil();
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
    /**
     * Mois affiché par défaut : le dernier mois de la période qui contient de l'activité (sorties) pour le district courant,
     * sinon pour les districts accessibles ; à défaut, le mois de la date de fin (ou le mois courant sans période).
     * Évite d'ouvrir les indicateurs sur un mois vide quand la période dépasse la dernière activité.
     */
    public static function defaultMonthKey(): string
    {
        if (! config('logimaster.default_period.until')) {
            return CarbonImmutable::now()->format('Y-m');
        }
        $until = self::defaultUntil();
        $from = config('logimaster.default_period.from') ? self::defaultFrom() : $until->subYears(5);
        $tenant = \Filament\Facades\Filament::getTenant();
        $last = self::lastActivity($tenant ? [$tenant->getKey()] : self::accessibleDistricts()->pluck('id')->all(), $from->startOfDay(), $until->endOfDay());

        return $last ? CarbonImmutable::parse($last)->format('Y-m') : $until->format('Y-m');
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

    /**
     * Date de la dernière sortie des districts donnés sur la période. Requête directe : la limitation automatique de Filament
     * au district du menu (tenant) ne doit pas s'appliquer quand les filtres visent d'autres districts.
     */
    private static function lastActivity(array $districtIds, CarbonImmutable $from, CarbonImmutable $until): ?string
    {
        return \Illuminate\Support\Facades\DB::table('sorties_vehicules')->whereNull('deleted_at')->whereIn('district_id', $districtIds)
            ->whereBetween('date_sortie', [$from->toDateString(), $until->toDateString()])->max('date_sortie');
    }

    /**
     * Mois des indicateurs DDKM : filtre « Mois des indicateurs » (« periode ») s'il est choisi, sinon le dernier mois de la
     * période qui a de l'activité (jamais un mois vide simplement parce que la date « Au » le contient).
     */
    public static function indicatorMonth(?array $filters): CarbonImmutable
    {
        if (! empty($filters['periode'])) {
            return CarbonImmutable::createFromFormat('Y-m', $filters['periode'])->startOfMonth();
        }
        if ($filters !== null && (array_key_exists('date_from', $filters) || array_key_exists('date_until', $filters) || ! empty($filters['district_id']) || ! empty($filters['region_id']) || ! empty($filters['pres_id']))) {
            // automatique : dernier mois avec des sorties, dans la période Du/Au et pour les districts filtrés
            $from = ! empty($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'])->startOfDay() : self::defaultFrom();
            $until = ! empty($filters['date_until']) ? CarbonImmutable::parse($filters['date_until'])->endOfDay() : self::defaultUntil();
            $last = self::lastActivity(self::districtIds($filters), $from, $until);

            return ($last ? CarbonImmutable::parse($last) : $until)->startOfMonth();
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
