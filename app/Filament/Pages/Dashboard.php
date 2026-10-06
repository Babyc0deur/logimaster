<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopeFilters;
use App\Filament\Pages\Indicators\Widgets\IndicatorDetail;
use App\Filament\Pages\Indicators\Widgets\IndicatorHistory;
use App\Filament\Widgets\FleetStatusDonut;
use App\Filament\Widgets\FuelChart;
use App\Filament\Widgets\IndicatorCards;
use App\Filament\Widgets\MaintenanceAlerts;
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
 * Tableau de bord unique : les 9 indicateurs DDKM (cartes, détail et évolution de l'indicateur choisi),
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
                        ->default(DashboardFilters::dashboardFrom())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(fn (Get $get) => $get('date_until'))
                        ->live(),

                    DatePicker::make('date_until')
                        ->label('Au')
                        ->default(DashboardFilters::dashboardUntil())
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
        return md5(static::class).'_v4_'.(Filament::getTenant()?->getKey() ?? 'global').'_filters';
    }

    public static function canSeeIndicators(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    public function getWidgets(): array
    {
        $indicators = self::canSeeIndicators() ? [IndicatorCards::class, IndicatorDetail::class, IndicatorHistory::class, \App\Filament\Widgets\FleetAnalysis::class] : [];

        // pas de bandeau « Total véhicules / Distance / Carburant / Sorties » : déjà dans « Immobilisation des véhicules » et les cartes DDKM
        // « Immobilisation des véhicules » et « Dépenses carburant » côte à côte, puis les alertes de maintenance
        return [...$indicators, FleetStatusDonut::class, FuelChart::class, MaintenanceAlerts::class];
    }
}
