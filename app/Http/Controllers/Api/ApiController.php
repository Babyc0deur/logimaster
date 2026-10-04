<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function requirePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403, "Permission requise : {$permission}");
    }

    /** Restreint une requête aux districts accessibles par l'utilisateur courant. */
    protected function scoped(Request $request, Builder $query, string $column = 'district_id'): Builder
    {
        $ids = $request->user()->accessibleDistrictIds();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    protected function assertDistrictAccess(Request $request, ?string $districtId): void
    {
        abort_unless($districtId && $request->user()->canAccessDistrict($districtId), 403, 'District hors périmètre.');
    }

    /**
     * Districts visés par une requête de lecture agrégée : district_id, region_id, scope=national,
     * ou par défaut l'ensemble des districts accessibles. null = tous.
     *
     * @return array<int, string>|null
     */
    protected function requestedDistrictIds(Request $request): ?array
    {
        $accessible = $request->user()->accessibleDistrictIds();

        if ($id = $request->query('district_id')) {
            $this->assertDistrictAccess($request, $id);

            return [$id];
        }

        if ($region = $request->query('region_id')) {
            $ids = District::where('region_id', $region)->pluck('id')->all();

            return $accessible === null ? $ids : array_values(array_intersect($ids, $accessible));
        }

        return $accessible;
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query('per_page', 25)));
    }
}
