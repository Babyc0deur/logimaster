<?php

namespace App\Filament\Widgets;

use App\Domain\Fleet\MaintenancePlanner;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Alertes prioritaires du dashboard : vidanges, CT, assurances, documents, immobilisations > 7 jours. */
class MaintenanceAlerts extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.maintenance-alerts';

    protected function getViewData(): array
    {
        return ['alerts' => \App\Domain\Fleet\AlertCenter::all(DashboardFilters::districtIds($this->pageFilters))->take(12)];
    }
}
