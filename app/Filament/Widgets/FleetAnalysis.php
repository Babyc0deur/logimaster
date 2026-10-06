<?php

namespace App\Filament\Widgets;

use App\Domain\Fleet\FleetAnalytics;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Tableau de bord — analyse par véhicule et par motif, sur le même mois et les mêmes districts que les cartes DDKM :
 * utilisation de chaque véhicule, répartition de l'utilisation / des distances / du carburant par motif et par véhicule,
 * utilisation rationnelle du carburant par véhicule. Graphiques homogènes (barres horizontales, mêmes couleurs par motif).
 */
class FleetAnalysis extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;   // affiché seulement par le tableau de bord

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.fleet-analysis';

    /** Véhicules affichés par graphique (les plus significatifs d'abord). */
    public const TOP = 12;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    protected function getViewData(): array
    {
        $month = DashboardFilters::indicatorMonth($this->pageFilters);
        $a = app(FleetAnalytics::class)->compute(DashboardFilters::districtIds($this->pageFilters), $month->startOfMonth(), $month->endOfMonth());
        $panels = FleetAnalytics::panels($a, self::TOP);

        return ['month' => $month, 'panels' => $panels];
    }
}
