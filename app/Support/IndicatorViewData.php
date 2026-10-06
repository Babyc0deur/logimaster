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
     * Indicateurs toujours à jour à l'affichage :
     * - mois jamais calculé pour un district (nouveau district, mois pas encore passé par le calcul de nuit) : calculé ;
     * - mois en cours : recalculé pour chaque district qui a une saisie plus récente que son dernier calcul (sortie,
     *   livraison, plein, vidange, immobilisation, dépense, planning, véhicule). Un district sans changement n'est pas recalculé,
     *   ce qui garde la page rapide même à l'échelle nationale.
     * Les mois passés restent tenus à jour chaque nuit (01:00) et après chaque import.
     */
    private static function computeMissing(array $ids, \Carbon\CarbonImmutable $month): void
    {
        if ($ids === [] || $month->greaterThan(\Carbon\CarbonImmutable::now()->endOfMonth())) {
            return;
        }
        $computed = \App\Models\IndicatorSnapshot::whereDate('period', $month->startOfMonth()->toDateString())->whereIn('district_id', $ids)
            ->selectRaw('district_id, min(computed_at) as at')->groupBy('district_id')->pluck('at', 'district_id');

        $todo = array_slice(array_values(array_diff($ids, $computed->keys()->all())), 0, 15);   // jamais calculés
        if ($month->isSameMonth(\Carbon\CarbonImmutable::now())) {
            $changed = self::lastChanges($computed->keys()->all());
            foreach ($computed as $districtId => $at) {
                if (isset($changed[$districtId]) && $changed[$districtId] > $at) {
                    $todo[] = $districtId;
                }
            }
        }
        $service = app(IndicatorService::class);
        foreach (array_unique($todo) as $id) {
            $service->computeForDistrict($id, $month);
        }
    }

    /** Date de la dernière saisie par district (toutes les données qui entrent dans les indicateurs). @return array<string, string> */
    private static function lastChanges(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $db = \Illuminate\Support\Facades\DB::connection();
        $last = [];
        $keep = function ($rows) use (&$last) {
            foreach ($rows as $district => $at) {
                if ($at !== null && (! isset($last[$district]) || $at > $last[$district])) {
                    $last[$district] = (string) $at;
                }
            }
        };
        foreach (['sorties_vehicules', 'ravitaillements', 'chronogrammes', 'immobilisations', 'vidanges', 'expenses', 'vehicles'] as $table) {
            $keep($db->table($table)->whereIn('district_id', $ids)->selectRaw('district_id, max(updated_at) as at')->groupBy('district_id')->pluck('at', 'district_id'));
        }
        $keep($db->table('livraisons_espc')->join('chronogrammes', 'chronogrammes.id', '=', 'livraisons_espc.chronogramme_id')
            ->whereIn('chronogrammes.district_id', $ids)->selectRaw('chronogrammes.district_id as district_id, max(livraisons_espc.updated_at) as at')
            ->groupBy('chronogrammes.district_id')->pluck('at', 'district_id'));

        return $last;
    }
}
