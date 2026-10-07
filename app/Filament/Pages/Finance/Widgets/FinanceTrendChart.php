<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Domain\Finance\BudgetTracker;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FinanceTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendance sur 6 mois';

    protected function getData(): array
    {
        $ids = DashboardFilters::districtIds($this->pageFilters);
        [$month] = DashboardFilters::month($this->pageFilters);
        $tracker = app(BudgetTracker::class);
        $labels = $spent = $alloue = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $month->subMonths($i);
            $s = $tracker->monthSummary($ids, $m);
            $labels[] = ucfirst($m->translatedFormat('M y'));
            $spent[] = round($s['depense']);
            $alloue[] = $s['alloue'] ?: null;
        }

        return ['labels' => $labels, 'datasets' => [
            ['label' => 'Dépenses', 'data' => $spent, 'borderColor' => '#f59e0b', 'backgroundColor' => 'rgba(245,158,11,.15)', 'fill' => true],
            ['label' => 'Budget alloué', 'data' => $alloue, 'borderColor' => '#c2410c', 'borderDash' => [6, 4], 'spanGaps' => true, 'fill' => false],
        ]];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
