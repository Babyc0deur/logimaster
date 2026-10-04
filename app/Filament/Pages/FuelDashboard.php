<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Fuel\Widgets\FuelByMotifChart;
use App\Filament\Pages\Fuel\Widgets\FuelByVehicleChart;
use App\Filament\Pages\Fuel\Widgets\FuelCostPerKmChart;
use App\Filament\Pages\Fuel\Widgets\FuelKpis;
use App\Filament\Pages\Fuel\Widgets\FuelMonthlyChart;
use App\Support\DashboardFilters;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FuelDashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_ravitaillements');
    }

    use HasFiltersForm;

    protected static string $routePath = 'carburant';

    protected static ?string $slug = 'carburant';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?string $navigationLabel = 'Tableau de bord carburant';

    protected static string|\UnitEnum|null $navigationGroup = 'Carburant';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Carburant';

    public function getWidgets(): array
    {
        return [FuelKpis::class, FuelByVehicleChart::class, FuelByMotifChart::class, FuelMonthlyChart::class, FuelCostPerKmChart::class];
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtres')->columnSpanFull()->columns(['default' => 1, 'sm' => 2])->schema([
                Select::make('periode')->label('Mois')->native(false)->live()
                    ->default(DashboardFilters::defaultMonthKey())
                    ->options(DashboardFilters::monthOptions(12)),
                Select::make('district_id')->label('District')->placeholder('Tous les districts')->live()
                    ->default(fn () => Filament::getTenant()?->getKey())
                    ->options(fn () => DashboardFilters::accessibleDistricts()->orderBy('name')->pluck('name', 'id')),
            ]),
        ]);
    }
}
