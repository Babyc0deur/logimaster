<?php

namespace App\Domain\Indicators;

use App\Models\Chronogramme;
use App\Models\LivraisonEspc;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Livraisons de sites prévues au chronogramme sur une période (sorties planifiées non annulées, déjà échues). */
class Deliveries
{
    /** @return Collection<int, Chronogramme> plans avec livraisons et site chargés */
    public static function plans(string $districtId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Chronogramme::with(['livraisons.espc:id,nom', 'circuit:id,nom'])
            ->where('district_id', $districtId)->where('statut', '!=', 'annulee')
            ->whereDateBetween('date_prevue', $start, min($end, CarbonImmutable::today()))
            ->orderBy('date_prevue')->get();
    }

    /** Une livraison est « selon planning » si le site est livré au plus tard à la date prévue. */
    public static function onSchedule(LivraisonEspc $l, Chronogramme $plan): bool
    {
        return $l->statut === 'livre' && ($l->date_livraison === null || $l->date_livraison->lte($plan->date_prevue));
    }
}
