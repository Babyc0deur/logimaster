<?php

namespace App\Filament\Pages\Fuel\Widgets;

use App\Domain\Fuel\FuelAnalyzer;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FuelByVehicleChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Consommation par véhicule (litres)';

    protected function getData(): array
    {
        [$from, $to] = DashboardFilters::month($this->pageFilters);
        $rows = app(FuelAnalyzer::class)->perVehicle(DashboardFilters::districtIds($this->pageFilters), $from, $to)->where('litres', '>', 0);

        return [
            'labels' => $rows->map(fn ($r) => $r['vehicle']->immatriculation)->values()->all(),
            'datasets' => [['label' => 'Litres', 'data' => $rows->pluck('litres')->values()->all(), 'backgroundColor' => 'rgba(37,99,235,.65)']],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
