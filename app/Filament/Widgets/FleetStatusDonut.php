<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** État de la flotte : véhicules disponibles / en mission / immobilisés / hors service. */
class FleetStatusDonut extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $heading = 'État de la flotte';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $counts = Vehicle::whereIn('district_id', DashboardFilters::districtIds($this->pageFilters))
            ->selectRaw('statut, count(*) as n')->groupBy('statut')->pluck('n', 'statut');

        $labels = ['disponible' => 'Disponibles', 'en_mission' => 'En mission', 'en_maintenance' => 'Immobilisés', 'hors_service' => 'Hors service'];
        $colors = ['disponible' => '#10b981', 'en_mission' => '#f59e0b', 'en_maintenance' => '#ef4444', 'hors_service' => '#6b7280'];

        return [
            'labels' => array_values($labels),
            'datasets' => [[
                'data' => array_map(fn ($k) => (int) ($counts[$k] ?? 0), array_keys($labels)),
                'backgroundColor' => array_values($colors),
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
