<?php

namespace App\Filament\Pages\Fuel\Widgets;

use App\Domain\Fuel\FuelAnalyzer;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FuelMonthlyChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Évolution mensuelle (12 mois)';

    protected function getData(): array
    {
        $series = app(FuelAnalyzer::class)->monthlySeries(DashboardFilters::districtIds($this->pageFilters), 12);

        return [
            'labels' => array_column($series, 'label'),
            'datasets' => [
                ['label' => 'Litres', 'data' => array_column($series, 'litres'), 'borderColor' => '#c2410c', 'yAxisID' => 'y'],
                ['label' => 'Coût (FCFA)', 'data' => array_column($series, 'cout'), 'borderColor' => '#f59e0b', 'yAxisID' => 'y1'],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['position' => 'left'], 'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
