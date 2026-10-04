<?php

namespace App\Domain\Fleet;

use App\Models\Driver;
use App\Models\Personnel;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;

/** Statistiques d'un chauffeur ou d'un membre du personnel (sorties, distance, consommation moyenne, dernière mission). */
class DriverStats
{
    /** @return array{nb_sorties: int, distance: float, litres: float, consommation_moyenne: ?float, derniere_sortie: ?string, vehicule_frequent: ?string} */
    public function forDriver(Driver $driver): array
    {
        $sorties = SortieVehicule::with('vehicle:id,immatriculation')->where('driver_id', $driver->id)->where('statut', '!=', 'annulee')->get();
        $closed = $sorties->whereNotNull('km_arrivee');
        $distance = (float) $closed->sum(fn ($s) => $s->km_arrivee - $s->km_depart);
        $litres = (float) Ravitaillement::whereIn('sortie_id', $closed->pluck('id'))->sum('litres');

        return [
            'nb_sorties' => $sorties->count(),
            'distance' => $distance,
            'litres' => $litres,
            'consommation_moyenne' => $distance > 0 && $litres > 0 ? round($litres / $distance * 100, 1) : null,
            'derniere_sortie' => $sorties->max('date_sortie')?->toDateString(),
            'vehicule_frequent' => $sorties->groupBy(fn ($s) => $s->vehicle?->immatriculation)->map->count()->sortDesc()->keys()->first(),
        ];
    }

    /** @return array{nb_missions: int, comme_chef: int, comme_passager: int, distance: float, derniere_mission: ?string} */
    public function forPersonnel(Personnel $p): array
    {
        $chef = SortieVehicule::where('chef_mission_id', $p->id)->where('statut', '!=', 'annulee')->get();
        $passager = $p->sortiesEnTantQuePassager()->where('sorties_vehicules.statut', '!=', 'annulee')->get();
        $all = $chef->merge($passager)->unique('id');

        return [
            'nb_missions' => $all->count(),
            'comme_chef' => $chef->count(),
            'comme_passager' => $passager->count(),
            'distance' => (float) $all->whereNotNull('km_arrivee')->sum(fn ($s) => $s->km_arrivee - $s->km_depart),
            'derniere_mission' => $all->max('date_sortie')?->toDateString(),
        ];
    }
}
