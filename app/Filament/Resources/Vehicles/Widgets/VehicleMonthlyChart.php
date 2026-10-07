<?php

namespace App\Filament\Resources\Vehicles\Widgets;

use App\Domain\Fleet\VehicleStats;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class VehicleMonthlyChart extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $heading = 'Distance et coûts — 12 derniers mois';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rows = $this->record ? app(VehicleStats::class)->monthly($this->record, 12) : [];

        return [
            'labels' => array_column($rows, 'label'),
            'datasets' => [
                ['type' => 'bar', 'label' => 'Distance (km)', 'data' => array_column($rows, 'km'), 'backgroundColor' => 'rgba(194,65,12,.6)', 'yAxisID' => 'y'],
                ['type' => 'line', 'label' => 'Coûts (FCFA)', 'data' => array_column($rows, 'cout'), 'borderColor' => '#f59e0b', 'yAxisID' => 'y1'],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['position' => 'left'], 'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
