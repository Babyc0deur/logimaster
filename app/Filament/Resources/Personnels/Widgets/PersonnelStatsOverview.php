<?php

namespace App\Filament\Resources\Personnels\Widgets;

use App\Domain\Fleet\DriverStats;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class PersonnelStatsOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }
        $s = app(DriverStats::class)->forPersonnel($this->record);

        return [
            Stat::make('Participations', $s['nb_missions'])
                ->description($s['comme_chef'].' comme chef de mission · '.$s['comme_passager'].' comme passager')->icon('heroicon-o-user-group'),
            Stat::make('Distance cumulée', number_format($s['distance'], 0, ',', ' ').' km')->icon('heroicon-o-map'),
            Stat::make('Dernière mission', $s['derniere_mission'] ? Carbon::parse($s['derniere_mission'])->format('d/m/Y') : '—')->icon('heroicon-o-calendar'),
        ];
    }
}
