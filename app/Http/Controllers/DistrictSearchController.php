<?php

namespace App\Http\Controllers;

use App\Models\District;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Recherche de districts à la frappe pour le sélecteur du haut du menu (seuls les districts accessibles à l'utilisateur). */
class DistrictSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }
        $needle = '%'.str_replace([' ', '%', '_'], ['%', '', ''], mb_strtolower($q)).'%';
        $ids = $user->accessibleDistrictIds();
        $panel = Filament::getPanel('admin');

        $districts = District::query()->with('region:id,name')
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->whereRaw('lower(name) like ?', [$needle])
            ->orderBy('name')->limit(15)->get();

        return response()->json($districts->map(fn (District $d) => [
            'id' => $d->getKey(), 'name' => $d->name, 'region' => $d->region?->name, 'url' => $panel->getUrl($d),
        ])->values());
    }
}
