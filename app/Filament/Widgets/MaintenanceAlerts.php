<?php

namespace App\Filament\Widgets;

use App\Domain\Fleet\MaintenancePlanner;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Alertes prioritaires du dashboard : vidanges, CT, assurances, documents, immobilisations > 7 jours. */
class MaintenanceAlerts extends Widget
{
    protected static bool $isLazy = false;   // rendu avec la page : un seul démarrage du framework au lieu de plusieurs requêtes différées

    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.maintenance-alerts';

    protected function getViewData(): array
    {
        return ['alerts' => \App\Domain\Fleet\AlertCenter::all(DashboardFilters::districtIds($this->pageFilters))->take(12)];
    }
}
