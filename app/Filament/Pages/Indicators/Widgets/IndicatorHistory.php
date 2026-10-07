<?php

namespace App\Filament\Pages\Indicators\Widgets;

use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorService;
use App\Support\DashboardFilters;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Évolution sur 12 mois de l'indicateur sélectionné (avec son objectif). */
class IndicatorHistory extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        $key = $this->pageFilters['indicateur'] ?? 'distance_totale';

        return 'Évolution sur 12 mois — '.(IndicatorCatalog::all()[$key]['label'] ?? '');
    }

    protected function getData(): array
    {
        $key = $this->pageFilters['indicateur'] ?? 'distance_totale';
        $key = array_key_exists($key, IndicatorCatalog::all()) ? $key : 'distance_totale';
        $meta = IndicatorCatalog::get($key);
        $history = collect(app(IndicatorService::class)->history(DashboardFilters::districtIds($this->pageFilters), $key, 12, DashboardFilters::indicatorMonth($this->pageFilters)))->keyBy('period');

        $labels = [];
        $values = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = DashboardFilters::indicatorMonth($this->pageFilters)->subMonths($i);
            $labels[] = $m->translatedFormat('M y');
            $values[] = $history[$m->format('Y-m')]['value'] ?? null;
        }

        $datasets = [['label' => $meta['label'].' ('.$meta['unit'].')', 'data' => $values, 'borderColor' => '#c2410c', 'backgroundColor' => 'rgba(194,65,12,.15)', 'fill' => true, 'spanGaps' => true, 'tension' => .25]];
        if ($meta['target'] !== null) {
            $datasets[] = ['label' => 'Objectif', 'data' => array_fill(0, 12, $meta['target']), 'borderColor' => '#10b981', 'borderDash' => [6, 4], 'pointRadius' => 0, 'fill' => false];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
