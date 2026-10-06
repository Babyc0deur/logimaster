<?php

namespace App\Filament\Widgets;

use App\Models\Ravitaillement;
use App\Support\DashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class FuelChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Dépenses carburant sur la période';

    // demi-largeur, à côté de « Immobilisation des véhicules »
    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $ids = DashboardFilters::districtIds($this->pageFilters);
        [$from, $until] = DashboardFilters::period($this->pageFilters);

        $records = Ravitaillement::whereIn('district_id', $ids)
            ->whereDateBetween('date_ravitaillement', $from, $until)
            ->selectRaw('date_ravitaillement as date, SUM(litres * prix_unitaire) as total')
            ->groupBy('date')->orderBy('date')->get();

        return [
            'datasets' => [[
                'label' => 'Carburant (XOF)',
                'data' => $records->pluck('total')->all(),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                'fill' => true,
            ]],
            'labels' => $records->pluck('date')->map(fn ($d) => Carbon::parse($d)->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
