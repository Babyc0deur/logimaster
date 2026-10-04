<?php

namespace App\Filament\Resources\Drivers\Widgets;

use App\Domain\Fleet\DriverStats;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Statistiques de la fiche chauffeur : sorties, distance, consommation moyenne. */
class DriverStatsOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }
        $s = app(DriverStats::class)->forDriver($this->record);

        return [
            Stat::make('Nombre de sorties', $s['nb_sorties'])
                ->description($s['derniere_sortie'] ? 'Dernière : '.Carbon::parse($s['derniere_sortie'])->format('d/m/Y') : 'Aucune sortie')->icon('heroicon-o-map-pin'),
            Stat::make('Distance totale', number_format($s['distance'], 0, ',', ' ').' km')
                ->description($s['vehicule_frequent'] ? 'Véhicule le plus utilisé : '.$s['vehicule_frequent'] : null)->icon('heroicon-o-map'),
            Stat::make('Consommation moyenne', $s['consommation_moyenne'] !== null ? $s['consommation_moyenne'].' L/100km' : '—')
                ->description(number_format($s['litres'], 0, ',', ' ').' L ravitaillés')->icon('heroicon-o-fire'),
        ];
    }
}
