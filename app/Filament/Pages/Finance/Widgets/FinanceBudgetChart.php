<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Domain\Finance\BudgetTracker;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FinanceBudgetChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Budget vs dépenses par poste';

    protected function getData(): array
    {
        [$month] = DashboardFilters::month($this->pageFilters);
        $s = app(BudgetTracker::class)->monthSummary(DashboardFilters::districtIds($this->pageFilters), $month);
        $labels = ['carburant' => 'Carburant', 'maintenance' => 'Maintenance', 'autres' => 'Autres frais'];

        return [
            'labels' => array_values($labels),
            'datasets' => [
                ['label' => 'Alloué', 'data' => array_map(fn ($k) => $s['postes'][$k]['alloue'], array_keys($labels)), 'backgroundColor' => 'rgba(37,99,235,.55)'],
                ['label' => 'Dépensé', 'data' => array_map(fn ($k) => round($s['postes'][$k]['depense']), array_keys($labels)), 'backgroundColor' => 'rgba(245,158,11,.75)'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
