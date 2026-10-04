<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Domain\Finance\BudgetTracker;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Suivi par bailleur : coûts du mois (carburant, maintenance, autres) rattachés aux véhicules de chaque bailleur et budget alloué. */
class FinanceBailleurs extends Widget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.pages.finance.bailleurs';

    protected function getViewData(): array
    {
        [$month] = DashboardFilters::month($this->pageFilters);

        return [
            'month' => $month,
            'rows' => app(BudgetTracker::class)->byBailleur(DashboardFilters::districtIds($this->pageFilters), $month),
        ];
    }
}
