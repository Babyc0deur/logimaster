<?php

namespace App\Filament\Pages\Fuel\Widgets;

use App\Domain\Fuel\FuelAnalyzer;
use App\Domain\Indicators\Calculators\UtilisationRationnelleCarburantCalculator;
use App\Models\Setting;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FuelKpis extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ids = DashboardFilters::districtIds($this->pageFilters);
        [$from, $to] = DashboardFilters::month($this->pageFilters);
        $analyzer = app(FuelAnalyzer::class);

        $now = $analyzer->perVehicle($ids, $from, $to);
        $prev = $analyzer->perVehicle($ids, $from->subMonth()->startOfMonth(), $from->subMonth()->endOfMonth());
        $litres = $now->sum('litres');
        $cout = $now->sum('cout');
        $prevCout = $prev->sum('cout');
        $evolution = $prevCout > 0 ? round(($cout - $prevCout) / $prevCout * 100, 1) : null;

        $rational = ['numerator' => 0, 'denominator' => 0];
        foreach ($ids as $id) {
            $r = (new UtilisationRationnelleCarburantCalculator)->compute($id, $from, $to);
            $rational['numerator'] += $r->breakdown['numerator'];
            $rational['denominator'] += $r->breakdown['denominator'];
        }
        $taux = $rational['denominator'] > 0 ? round($rational['numerator'] / $rational['denominator'] * 100, 1) : null;

        $over = $now->where('surconsommation', true);
        $projection = $from->isSameMonth(now()) ? $analyzer->projection($ids) : null;

        return [
            Stat::make('Consommation du mois', number_format($litres, 0, ',', ' ').' L')
                ->description($projection ? 'Projection fin de mois : '.number_format($projection['litres'], 0, ',', ' ').' L' : $from->translatedFormat('F Y'))
                ->icon('heroicon-o-beaker')->color('info'),
            Stat::make('Coût carburant', number_format($cout, 0, ',', ' ').' FCFA')
                ->description($projection ? 'Projection : '.number_format($projection['cout'], 0, ',', ' ').' FCFA' : null)
                ->icon('heroicon-o-banknotes')->color('warning'),
            Stat::make('Évolution vs mois précédent', $evolution !== null ? sprintf('%+.1f %%', $evolution) : '—')
                ->description('Mois précédent : '.number_format($prevCout, 0, ',', ' ').' FCFA')
                ->descriptionIcon($evolution !== null && $evolution > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($evolution !== null && $evolution > 0 ? 'danger' : 'success'),
            Stat::make('Utilisation rationnelle', $taux !== null ? "{$taux} %" : '—')
                ->description('Carburant justifié par les km parcourus')->icon('heroicon-o-check-circle')
                ->color($taux !== null && $taux < 85 ? 'danger' : 'success'),
            Stat::make('Véhicules en surconsommation', $over->count())
                ->description($over->isEmpty() ? 'Seuil : '.Setting::get('seuil_surconsommation').' %' : $over->map(fn ($r) => $r['vehicle']->immatriculation)->take(4)->join(', '))
                ->icon('heroicon-o-exclamation-triangle')->color($over->isEmpty() ? 'success' : 'danger'),
        ];
    }
}
