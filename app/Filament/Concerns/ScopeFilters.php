<?php

namespace App\Filament\Concerns;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Support\DashboardFilters;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Filtres de périmètre en cascade PRES → Région → District, limités aux districts accessibles à l'utilisateur. */
trait ScopeFilters
{
    /** @return array<int, Select> */
    protected static function scopeFilterFields(): array
    {
        return [
            Select::make('pres_id')
                ->label('PRES')
                ->placeholder('Tous les PRES')
                ->default(fn () => Filament::getTenant()?->region?->pres_id)
                ->options(fn () => Pres::whereHas('regions', fn ($r) => $r->whereHas('districts', fn ($d) => $d->whereIn('id', DashboardFilters::accessibleDistricts()->select('id'))))
                    ->orderBy('name')->pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('region_id', null);
                    $set('district_id', null);
                }),

            Select::make('region_id')
                ->label('Région')
                ->placeholder('Toutes les régions')
                ->default(fn () => Filament::getTenant()?->region_id)
                ->options(fn (Get $get) => Region::query()
                    ->when($get('pres_id'), fn ($q, $pres) => $q->where('pres_id', $pres))
                    ->whereHas('districts', fn ($d) => $d->whereIn('id', DashboardFilters::accessibleDistricts()->select('id')))
                    ->orderBy('name')->pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $set('district_id', null);
                    if ($state && ($pres = Region::whereKey($state)->value('pres_id'))) {
                        $set('pres_id', $pres);
                    }
                }),

            Select::make('district_id')
                ->label('District')
                ->placeholder('Tous les districts')
                ->default(fn () => Filament::getTenant()?->getKey())
                ->options(fn (Get $get) => DashboardFilters::accessibleDistricts()
                    ->when($get('region_id'), fn ($q, $region) => $q->where('region_id', $region))
                    ->when(! $get('region_id') && $get('pres_id'), fn ($q) => $q->whereIn('region_id', Region::where('pres_id', $get('pres_id'))->select('id')))
                    ->orderBy('name')->pluck('name', 'id'))
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $district = $state ? District::with('region:id,pres_id')->find($state) : null;
                    if ($district) {
                        $set('region_id', $district->region_id);
                        $set('pres_id', $district->region?->pres_id);
                    }
                }),
        ];
    }
}
