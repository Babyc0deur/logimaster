<?php

namespace App\Filament\Resources\Espcs\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Historique des livraisons d'un ESPC : dernière livraison, total, taux de respect, retards. */
class EspcStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }
        $s = $this->record->livraisonStats();

        return [
            Stat::make('Dernière livraison', $s['derniere'] ? Carbon::parse($s['derniere'])->format('d/m/Y') : '—')->icon('heroicon-o-truck'),
            Stat::make('Livraisons', $s['livrees'].' / '.$s['planifiees'])->description('Livrées / planifiées (échues)')->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Taux de respect', $s['taux'] !== null ? round($s['taux']).' %' : '—')->description('Livré au plus tard à la date prévue')->icon('heroicon-o-check-badge')
                ->color($s['taux'] === null ? 'gray' : ($s['taux'] >= 0.9 ? 'success' : ($s['taux'] >= 0.75 ? 'warning' : 'danger'))),
            Stat::make('Retards / non livrées', $s['retards'].' / '.$s['non_livrees'])->icon('heroicon-o-exclamation-triangle')->color($s['retards'] + $s['non_livrees'] > 0 ? 'warning' : 'success'),
        ];
    }
}
