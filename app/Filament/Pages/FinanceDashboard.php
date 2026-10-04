<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopeFilters;
use App\Support\DashboardFilters;
use App\Filament\Pages\Finance\Widgets\BudgetAlertsList;
use App\Filament\Pages\Finance\Widgets\FinanceBailleurs;
use App\Filament\Pages\Finance\Widgets\FinanceBudgetChart;
use App\Filament\Pages\Finance\Widgets\FinanceKpis;
use App\Filament\Pages\Finance\Widgets\FinanceTrendChart;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Tableau de bord financier : budget vs dépenses par poste, prévision de fin de mois, tendances, bailleurs, alertes de dépassement. */
class FinanceDashboard extends BaseDashboard
{
    use HasFiltersForm, ScopeFilters;

    protected static string $routePath = 'finance';

    protected static ?string $slug = 'finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?string $navigationLabel = 'Tableau de bord financier';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Finance';

    public static function canAccess(): bool
    {
        return \App\Support\Modules::enabled('finance') && (bool) auth()->user()?->can('view_budgets');
    }

    public function getWidgets(): array
    {
        return [FinanceKpis::class, BudgetAlertsList::class, FinanceBudgetChart::class, FinanceTrendChart::class, FinanceBailleurs::class];
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtres')->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                ...self::scopeFilterFields(),
                Select::make('periode')->label('Mois')->native(false)->live()->default(DashboardFilters::defaultMonthKey())
                    ->options(DashboardFilters::monthOptions(18)),
            ]),
        ]);
    }
}
