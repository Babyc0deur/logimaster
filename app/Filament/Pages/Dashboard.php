<?php

namespace App\Filament\Pages;

use App\Domain\Indicators\IndicatorService;
use App\Filament\Concerns\ScopeFilters;
use App\Filament\Pages\Indicators\Widgets\IndicatorDetail;
use App\Filament\Pages\Indicators\Widgets\IndicatorHistory;
use App\Filament\Widgets\FleetStatsOverview;
use App\Filament\Widgets\FleetStatusDonut;
use App\Filament\Widgets\FuelChart;
use App\Filament\Widgets\IndicatorCards;
use App\Filament\Widgets\MaintenanceAlerts;
use App\Support\IndicatorViewData;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Region;
use App\Support\DashboardFilters;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Tableau de bord unique : chiffres de la flotte, les 9 indicateurs DDKM (cartes, détail et évolution de l'indicateur choisi),
 * état du parc, alertes de maintenance et carburant, avec les mêmes filtres de périmètre et de période.
 */
class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_dashboard');
    }

    use HasFiltersForm, ScopeFilters;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtres')
                ->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 5])
                ->components([
                    ...self::scopeFilterFields(),

                    DatePicker::make('date_from')
                        ->label('Du')
                        ->default(DashboardFilters::defaultFrom())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(fn (Get $get) => $get('date_until'))
                        ->live(),

                    DatePicker::make('date_until')
                        ->label('Au')
                        ->default(DashboardFilters::defaultUntil())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(fn (Get $get) => $get('date_from'))
                        ->live(),

                    // indicateur détaillé : choisi en cliquant sur sa carte (le mois des indicateurs suit le district et les dates)
                    \Filament\Forms\Components\Hidden::make('indicateur')->default('distance_totale'),
                ]),
        ]);
    }

    /** Filtres mémorisés par district : changer de district ne reprend pas le mois (souvent vide) choisi pour un autre. */
    public function getFiltersSessionKey(): string
    {
        return md5(static::class).'_v3_'.(Filament::getTenant()?->getKey() ?? 'global').'_filters';
    }

    public static function canSeeIndicators(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    public function getWidgets(): array
    {
        $indicators = self::canSeeIndicators() ? [IndicatorCards::class, IndicatorDetail::class, IndicatorHistory::class] : [];

        return [FleetStatsOverview::class, ...$indicators, FleetStatusDonut::class, MaintenanceAlerts::class, FuelChart::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculer')
                ->label('Recalculer les indicateurs du mois')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn () => self::canSeeIndicators() && auth()->user()->can('create_reports'))
                ->requiresConfirmation()
                ->modalDescription('Recalcule les 9 indicateurs DDKM du mois choisi pour les districts du périmètre (les calculs sont normalement automatiques chaque nuit).')
                ->action(function () {
                    $ids = DashboardFilters::districtIds($this->filters);
                    $month = DashboardFilters::indicatorMonth($this->filters);
                    $service = app(IndicatorService::class);
                    foreach ($ids as $id) {
                        $service->computeForDistrict($id, $month);
                    }
                    IndicatorViewData::flush();
                    Notification::make()->title(count($ids).' district(s) recalculé(s) — '.$month->translatedFormat('F Y'))->success()->send();
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
