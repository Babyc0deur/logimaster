<?php

namespace App\Filament\Pages\Fuel\Widgets;

use App\Domain\Fuel\FuelAnalyzer;
use App\Models\SortieVehicule;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FuelByMotifChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Consommation par motif';

    protected function getData(): array
    {
        [$from, $to] = DashboardFilters::month($this->pageFilters);
        $rows = app(FuelAnalyzer::class)->byMotif(DashboardFilters::districtIds($this->pageFilters), $from, $to);

        return [
            'labels' => $rows->keys()->map(fn ($m) => SortieVehicule::MOTIFS[$m] ?? 'Non affecté')->all(),
            'datasets' => [[
                'data' => $rows->pluck('litres')->values()->all(),
                'backgroundColor' => ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#6b7280'],
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
