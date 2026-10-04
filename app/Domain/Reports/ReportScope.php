<?php

namespace App\Domain\Reports;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;

/** Périmètre d'un rapport (district / région / PRES / tout ce que l'utilisateur peut voir), toujours borné aux droits de l'utilisateur. */
final class ReportScope
{
    /**
     * @param  array<string, mixed>|null  $scope  ['district_id'|'region_id'|'pres_id' => uuid] ou [] = tout le périmètre de l'utilisateur
     * @return array<int, string>
     */
    public static function ids(?array $scope, User $user): array
    {
        $accessible = $user->accessibleDistrictIds();
        $query = District::query();
        if (! empty($scope['district_id'])) {
            $query->whereKey($scope['district_id']);
        } elseif (! empty($scope['region_id'])) {
            $query->where('region_id', $scope['region_id']);
        } elseif (! empty($scope['pres_id'])) {
            $query->whereIn('region_id', Region::where('pres_id', $scope['pres_id'])->select('id'));
        }
        $ids = $query->pluck('id')->all();

        return $accessible === null ? $ids : array_values(array_intersect($ids, $accessible));
    }

    public static function label(?array $scope, int $count): string
    {
        return match (true) {
            ! empty($scope['district_id']) => 'District '.(District::find($scope['district_id'])?->name ?? ''),
            ! empty($scope['region_id']) => 'Région '.(Region::find($scope['region_id'])?->name ?? ''),
            ! empty($scope['pres_id']) => (Pres::find($scope['pres_id'])?->name ?? 'PRES'),
            default => $count === 1 ? 'District '.(District::first()?->name ?? '') : "{$count} districts",
        };
    }
}
