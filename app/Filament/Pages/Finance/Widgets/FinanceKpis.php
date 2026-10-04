<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Domain\Finance\BudgetTracker;
use App\Models\Facture;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceKpis extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ids = DashboardFilters::districtIds($this->pageFilters);
        [$month] = DashboardFilters::month($this->pageFilters);
        $s = app(BudgetTracker::class)->monthSummary($ids, $month);
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' FCFA';
        $factures = Facture::whereIn('district_id', $ids);

        return [
            Stat::make('Budget alloué', $fmt($s['alloue']))->description(ucfirst($month->translatedFormat('F Y')))->icon('heroicon-o-calculator')->color('info'),
            Stat::make('Dépenses du mois', $fmt($s['depense']))
                ->description($s['pct'] !== null ? round($s['pct']).' % du budget' : 'Aucun budget saisi')->icon('heroicon-o-banknotes')
                ->color($s['pct'] === null ? 'gray' : ($s['pct'] > 100 ? 'danger' : ($s['pct'] >= 80 ? 'warning' : 'success'))),
            Stat::make($s['reste'] >= 0 ? 'Reste disponible' : 'Dépassement', $fmt(abs($s['reste'])))
                ->description('Prévision fin de mois : '.$fmt($s['prevision']))->icon('heroicon-o-scale')
                ->color($s['reste'] < 0 || ($s['alloue'] > 0 && $s['prevision'] > $s['alloue']) ? 'danger' : 'success'),
            Stat::make('Alertes budget', $s['alertes'])->description('Dépassements constatés ou prévus')->icon('heroicon-o-exclamation-triangle')->color($s['alertes'] ? 'danger' : 'success'),
            Stat::make('Factures à traiter', (clone $factures)->whereIn('statut', ['a_valider', 'validee'])->count())
                ->description($fmt((clone $factures)->whereIn('statut', ['a_valider', 'validee'])->sum('montant')).' en attente de validation ou de paiement')
                ->icon('heroicon-o-receipt-percent')->color('warning'),
        ];
    }
}
