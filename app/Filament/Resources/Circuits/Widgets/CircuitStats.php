<?php

namespace App\Filament\Resources\Circuits\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Suivi du respect d'un circuit : sorties, respect, km réalisés vs prévus, dernier passage. */
class CircuitStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }
        $sorties = $this->record->sorties()->where('statut', '!=', 'annulee')->get();
        $evalues = $sorties->whereNotNull('circuit_respecte');
        $respectees = $evalues->where('circuit_respecte', true)->count();
        $closed = $sorties->whereNotNull('km_arrivee');
        $avg = $closed->isNotEmpty() ? $closed->avg(fn ($s) => $s->km_arrivee - $s->km_depart) : null;
        $prevu = $this->record->distance_totale !== null ? (float) $this->record->distance_totale : null;

        return [
            Stat::make('Sorties sur ce circuit', $sorties->count())->description($sorties->max('date_sortie') ? 'Dernier passage : '.$sorties->max('date_sortie')->format('d/m/Y') : 'Jamais parcouru')->icon('heroicon-o-map-pin'),
            Stat::make('Circuit respecté', $evalues->isNotEmpty() ? round($respectees / $evalues->count() * 100).' %' : '—')
                ->description("{$respectees}/{$evalues->count()} sorties conformes")->icon('heroicon-o-check-circle')
                ->color($evalues->isEmpty() ? 'gray' : ($respectees / $evalues->count() >= 0.9 ? 'success' : 'warning')),
            Stat::make('Distance réalisée (moyenne)', $avg !== null ? number_format($avg, 0, ',', ' ').' km' : '—')
                ->description($prevu !== null && $avg !== null ? sprintf('Prévu %s km (%+.0f km)', number_format($prevu, 0, ',', ' '), $avg - $prevu) : ($prevu ? 'Prévu '.number_format($prevu, 0, ',', ' ').' km' : null))
                ->icon('heroicon-o-arrows-right-left')->color($prevu !== null && $avg !== null && $avg > $prevu * 1.1 ? 'warning' : 'gray'),
        ];
    }
}
