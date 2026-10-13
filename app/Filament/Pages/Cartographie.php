<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\FleetMapSimulation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use App\Models\District;
use App\Models\Region;
use App\Support\DashboardFilters;

/** Suivi en temps réel (ex « Cartographie ») du district (OpenStreetMap) : les véhicules parcourent leur circuit en simulation, site par site. */
class Cartographie extends Page
{
    protected string $view = 'filament.pages.cartographie';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Suivi en temps réel';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Suivi en temps réel';

    protected static ?string $slug = 'suivi-temps-reel';

    /** Région choisie ('' = rester sur le district du menu). */
    public string $region = '';

    /** District choisi ('' = tous les districts de la région, ou le district du menu si aucune région). */
    public string $district = '';

    public function updatedRegion(): void
    {
        $this->district = '';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    public function getSubheading(): ?string
    {
        return 'Simulation du déplacement des véhicules sur leurs circuits, par district ou par région — fond de carte OpenStreetMap.';
    }

    public function getViewData(): array
    {
        $regions = Region::whereHas('districts', fn ($q) => $q->whereIn('id', DashboardFilters::accessibleDistricts()->select('id')))->orderBy('name')->pluck('name', 'id');
        $districts = $this->region !== ''
            ? DashboardFilters::accessibleDistricts()->where('region_id', $this->region)->orderBy('name')->pluck('name', 'id')
            : collect();

        $scope = collect([Filament::getTenant()]);
        if ($this->region !== '' && $regions->has($this->region)) {
            $scope = DashboardFilters::accessibleDistricts()->where('region_id', $this->region)
                ->when($this->district !== '', fn ($q) => $q->whereKey($this->district))->orderBy('name')->get();
        }
        $scope = $scope->filter()->values();
        $scope->each(fn (District $d) => $d->loadMissing('region:id,name'));

        return [
            'map' => app(FleetMapSimulation::class)->buildMany($scope),
            'regions' => $regions,
            'districts' => $districts,
            'tenantName' => Filament::getTenant()?->name,
        ];
    }
}
