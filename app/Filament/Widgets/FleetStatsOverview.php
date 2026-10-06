<?php

namespace App\Filament\Widgets;

use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FleetStatsOverview extends BaseWidget
{
    /** Retiré du tableau de bord (doublon des cartes DDKM et de « État de la flotte ») ; conservé pour d'autres pages éventuelles. */
    protected static bool $isDiscovered = false;

    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $ids = DashboardFilters::districtIds($this->pageFilters);
        [$from, $until] = DashboardFilters::period($this->pageFilters);
        $scope = DashboardFilters::scopeLabel($this->pageFilters);
        $period = $from->format('d/m/Y').' → '.$until->format('d/m/Y');

        $vehicles = Vehicle::whereIn('district_id', $ids);
        $km = (float) SortieVehicule::whereIn('district_id', $ids)->whereNotNull('km_arrivee')
            ->whereDateBetween('date_sortie', $from, $until)->sum(DB::raw('km_arrivee - km_depart'));
        $fuel = Ravitaillement::whereIn('district_id', $ids)->whereDateBetween('date_ravitaillement', $from, $until);

        return [
            Stat::make('Total Véhicules', (clone $vehicles)->count())
                ->description($scope)->icon('heroicon-o-truck')->color('info'),
            Stat::make('En Mission', (clone $vehicles)->where('statut', 'en_mission')->count())
                ->description('Sur le terrain')->icon('heroicon-o-map-pin')->color('warning'),
            Stat::make('En Maintenance', (clone $vehicles)->where('statut', 'en_maintenance')->count())
                ->description('Immobilisés : '.Immobilisation::whereIn('district_id', $ids)->where('statut', 'en_cours')->count().' en cours')
                ->icon('heroicon-o-wrench')->color('danger'),
            Stat::make('Distance parcourue', number_format($km, 0, ',', ' ').' km')
                ->description($period)->icon('heroicon-o-arrow-trending-up')->color('success'),
            Stat::make('Carburant', number_format((float) (clone $fuel)->sum('litres'), 0, ',', ' ').' L')
                ->description(number_format((float) (clone $fuel)->sum(DB::raw('litres * prix_unitaire')), 0, ',', ' ').' XOF · '.$period)
                ->icon('heroicon-o-fire')->color('warning'),
            Stat::make('Sorties', SortieVehicule::whereIn('district_id', $ids)->whereDateBetween('date_sortie', $from, $until)->count())
                ->description($period)->icon('heroicon-o-clipboard-document-list')->color('primary'),
        ];
    }
}
