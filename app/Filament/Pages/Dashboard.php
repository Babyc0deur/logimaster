<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopeFilters;
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
                ]),
        ]);
    }
}
