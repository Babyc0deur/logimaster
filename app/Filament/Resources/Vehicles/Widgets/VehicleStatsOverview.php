<?php

namespace App\Filament\Resources\Vehicles\Widgets;

use App\Domain\Fleet\VehicleStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class VehicleStatsOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }
        $service = app(VehicleStats::class);
        $total = $service->forPeriod($this->record, CarbonImmutable::parse('2000-01-01'), CarbonImmutable::now());
        $recent = $service->forPeriod($this->record, CarbonImmutable::now()->subDays(29), CarbonImmutable::now());
        $theo = $total['consommation_theorique'];
        $reelle = $total['consommation_reelle'];

        return [
            Stat::make('Distance totale parcourue', number_format($total['km'], 0, ',', ' ').' km')
                ->description($total['nb_sorties'].' sortie(s)')->icon('heroicon-o-map'),
            Stat::make('Consommation moyenne', $reelle !== null ? "{$reelle} L/100km" : '—')
                ->description($theo ? "Théorique : {$theo} L/100km" : 'Théorique non renseignée')
                ->color($reelle !== null && $theo && $reelle > $theo * 1.15 ? 'danger' : 'success')->icon('heroicon-o-fire'),
            Stat::make("Taux d'utilisation (30 j)", $recent['taux_utilisation'].' %')->icon('heroicon-o-chart-bar')
                ->color($recent['taux_utilisation'] < 60 ? 'warning' : 'success'),
            Stat::make('Taux de disponibilité (30 j)', $recent['taux_disponibilite'].' %')->icon('heroicon-o-check-circle')
                ->color($recent['taux_disponibilite'] < 90 ? 'danger' : 'success'),
            Stat::make('Coût au kilomètre', $total['cout_km'] !== null ? number_format($total['cout_km'], 0, ',', ' ').' FCFA/km' : '—')
                ->description('Carburant + maintenance : '.number_format($total['cout_total'], 0, ',', ' ').' FCFA')->icon('heroicon-o-banknotes'),
        ];
    }
}
