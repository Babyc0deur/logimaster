<?php

namespace App\Filament\Widgets;

use App\Domain\Fleet\FleetAnalytics;
use App\Domain\Reports\ChartSvg;
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
    private const TOP = 12;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    protected function getViewData(): array
    {
        $month = DashboardFilters::indicatorMonth($this->pageFilters);
        $a = app(FleetAnalytics::class)->compute(DashboardFilters::districtIds($this->pageFilters), $month->startOfMonth(), $month->endOfMonth());
        $motifs = $a['motifs'];
        $top = fn (array $rows) => array_slice($rows, 0, self::TOP, true);
        $more = fn (array $rows) => max(0, count($rows) - self::TOP);
        $ok = '#22c55e';
        $warn = '#f59e0b';
        $bad = '#ef4444';

        $util = $top($a['utilisation']);
        $rat = $top(array_filter($a['rationnel'], fn ($r) => $r['taux'] !== null));

        $panels = [
            [
                'title' => 'Taux d\'utilisation de chaque véhicule',
                'help' => 'Jours avec au moins une sortie / jours disponibles (immobilisations déduites). Objectif : 60 %.',
                'svg' => $util ? ChartSvg::hbars(array_keys($util), array_column($util, 'taux'), '%',
                    array_map(fn ($r) => $r['taux'] >= 60 ? $ok : ($r['taux'] >= 30 ? $warn : $bad), array_values($util)), 60) : null,
                'more' => $more($a['utilisation']),
            ],
            [
                'title' => 'Répartition de l\'utilisation par motif et par véhicule',
                'help' => 'Jours d\'utilisation de chaque véhicule, ventilés par motif de déplacement.',
                'svg' => $a['repartition'] ? ChartSvg::hstack($top($a['repartition']), $motifs, 'j') : null,
                'more' => $more($a['repartition']),
            ],
            [
                'title' => 'Distance parcourue par motif et par véhicule',
                'help' => 'Kilomètres des sorties (compteur de retour − départ), ventilés par motif.',
                'svg' => $a['distance'] ? ChartSvg::hstack($top($a['distance']), $motifs, 'km') : null,
                'more' => $more($a['distance']),
            ],
            [
                'title' => 'Carburant consommé par motif et par véhicule',
                'help' => 'Litres des pleins, rattachés au motif de la sortie.',
                'svg' => $a['carburant'] ? ChartSvg::hstack($top($a['carburant']), $motifs, 'L') : null,
                'more' => $more($a['carburant']),
            ],
            [
                'title' => 'Utilisation rationnelle du carburant par véhicule',
                'help' => 'Consommation théorique (km × consommation du véhicule) / litres réellement pris. 100 % = conforme ; en dessous = surconsommation. Objectif : 85 %.',
                'svg' => $rat ? ChartSvg::hbars(array_keys($rat), array_map(fn ($r) => min(200, $r['taux']), array_values($rat)), '%',
                    array_map(fn ($r) => $r['taux'] >= 85 ? $ok : ($r['taux'] >= 70 ? $warn : $bad), array_values($rat)), 85) : null,
                'more' => $more(array_filter($a['rationnel'], fn ($r) => $r['taux'] !== null)),
                'note' => ($n = count(array_filter($a['rationnel'], fn ($r) => $r['taux'] === null))) ? "{$n} véhicule(s) non évalué(s) : aucun plein déclaré sur la période, ou consommation théorique (L/100 km) non renseignée sur la fiche du véhicule." : null,
            ],
        ];

        return ['month' => $month, 'panels' => $panels];
    }
}
