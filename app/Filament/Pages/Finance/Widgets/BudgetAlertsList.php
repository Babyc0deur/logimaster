<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Domain\Finance\BudgetTracker;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class BudgetAlertsList extends Widget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.pages.finance.budget-alerts';

    protected function getViewData(): array
    {
        return ['alerts' => app(BudgetTracker::class)->alerts(DashboardFilters::districtIds($this->pageFilters))];
    }
}
