<?php

namespace App\Filament\Pages\Indicators\Widgets;

use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorDetails;
use App\Support\IndicatorViewData;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Détail de l'indicateur sélectionné : chiffres clés, tableaux (par motif, véhicule, circuit…), écarts et raisons. */
class IndicatorDetail extends Widget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.pages.indicators.indicator-detail';

    protected function getViewData(): array
    {
        $key = $this->pageFilters['indicateur'] ?? 'distance_totale';
        $key = array_key_exists($key, IndicatorCatalog::all()) ? $key : 'distance_totale';
        $data = IndicatorViewData::get($this->pageFilters);
        $row = $data['rows'][$key];
        $meta = IndicatorCatalog::get($key);

        return [
            'meta' => $meta,
            'month' => $data['month'],
            'empty' => $row['districts'] === 0,
            'value' => IndicatorCatalog::format($key, $row['value']),
            'color' => IndicatorCatalog::color($key, $row['value']),
            'previous' => $row['previous'] !== null ? IndicatorCatalog::format($key, $row['previous']) : null,
            'delta_pct' => $row['delta_pct'],
            'target' => $meta['target'] !== null ? ($meta['direction'] === 'down' ? '≤ ' : '≥ ').IndicatorCatalog::format($key, $meta['target']) : null,
            'districts' => $row['districts'],
            'detail' => IndicatorDetails::blocks($key, $row, $data['rows']['cout_global']),
        ];
    }
}
