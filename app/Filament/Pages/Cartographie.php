<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\FleetMapSimulation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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

    /** Région simulée : tous ses districts (limites de la région et de chaque district sur la carte). */
    private const REGION = 'NAWA';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    public function getSubheading(): ?string
    {
        return 'Simulation du déplacement des véhicules sur leurs circuits, région NAWA et ses districts — fond de carte OpenStreetMap.';
    }

    public function getViewData(): array
    {
        $scope = DashboardFilters::accessibleDistricts()->with('region:id,name')
            ->whereIn('region_id', Region::where('name', self::REGION)->select('id'))->orderBy('name')->get();
        if ($scope->isEmpty() && Filament::getTenant()) {
            $scope = collect([Filament::getTenant()->loadMissing('region:id,name')]);
        }

        return ['map' => app(FleetMapSimulation::class)->buildMany($scope)];
    }
}
