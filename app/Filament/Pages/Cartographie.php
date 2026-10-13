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

    /** Région dont les limites (et celles de ses districts) sont tracées. */
    private const REGION = 'NAWA';

    /** District dont les véhicules sont simulés. */
    private const DISTRICT = 'MEAGUI';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    public function getSubheading(): ?string
    {
        return 'Simulation du déplacement des véhicules sur leurs circuits, district de Méagui, région NAWA — fond de carte OpenStreetMap.';
    }

    public function getViewData(): array
    {
        $limits = DashboardFilters::accessibleDistricts()->with('region:id,name')
            ->whereIn('region_id', Region::where('name', self::REGION)->select('id'))->orderBy('name')->get();
        $scope = $limits->where('name', self::DISTRICT)->values();
        if ($scope->isEmpty() && Filament::getTenant()) {
            $scope = collect([Filament::getTenant()->loadMissing('region:id,name')]);
            $limits = $scope;
        }

        return ['map' => app(FleetMapSimulation::class)->buildMany($scope, 12, $limits)];
    }
}
