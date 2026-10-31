<?php

namespace App\Domain\Fleet;

use Illuminate\Support\Collection;

/** Toutes les alertes d'un périmètre : maintenance (vidanges, CT, assurances, documents, immobilisations). */
class AlertCenter
{
    /**
     * @param  array<int, string>|null  $districtIds
     * @return Collection<int, array<string, mixed>> triées par gravité
     */
    public static function all(?array $districtIds): Collection
    {
        $alerts = app(MaintenancePlanner::class)->alerts($districtIds);

        return $alerts
            ->sortBy(fn ($a) => $a['level']->rank())->values();
    }
}
