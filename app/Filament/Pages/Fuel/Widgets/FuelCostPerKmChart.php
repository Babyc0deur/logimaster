<?php

namespace App\Filament\Pages\Fuel\Widgets;

use App\Domain\Fuel\FuelAnalyzer;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FuelCostPerKmChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Coût carburant par kilomètre (FCFA/km)';

    protected function getData(): array
    {
        [$from, $to] = DashboardFilters::month($this->pageFilters);
        $rows = app(FuelAnalyzer::class)->perVehicle(DashboardFilters::districtIds($this->pageFilters), $from, $to)->whereNotNull('cout_km');

        return [
            'labels' => $rows->map(fn ($r) => $r['vehicle']->immatriculation)->values()->all(),
            'datasets' => [['label' => 'FCFA/km', 'data' => $rows->pluck('cout_km')->values()->all(), 'backgroundColor' => 'rgba(245,158,11,.7)']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
