<?php

namespace App\Filament\Resources\Factures\Widgets;

use App\Models\Facture;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FactureStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $q = fn (string $statut) => Facture::where('district_id', Filament::getTenant()->getKey())->where('statut', $statut);
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' FCFA';

        return [
            Stat::make('À valider', $q('a_valider')->count())->description($fmt($q('a_valider')->sum('montant')))->icon('heroicon-o-clock')->color('warning'),
            Stat::make('À payer (validées)', $q('validee')->count())->description($fmt($q('validee')->sum('montant')))->icon('heroicon-o-banknotes')->color('info'),
            Stat::make('Payées ce mois', $q('payee')->whereMonth('paye_at', now()->month)->whereYear('paye_at', now()->year)->count())
                ->description($fmt($q('payee')->whereMonth('paye_at', now()->month)->whereYear('paye_at', now()->year)->sum('montant')))->icon('heroicon-o-check-circle')->color('success'),
            Stat::make('Rejetées', $q('rejetee')->count())->description('À corriger et re-soumettre')->icon('heroicon-o-x-circle')->color('danger'),
        ];
    }
}
