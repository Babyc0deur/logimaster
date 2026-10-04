<?php

namespace App\Filament\Concerns;

use App\Models\Espc;

/** Lecture / enregistrement des étapes d'un circuit (table pivot ordonnée avec distance) depuis le formulaire. */
trait SyncsCircuitEtapes
{
    /** @return array<int, array{espc_id: string, distance_km: mixed}> */
    protected function loadEtapes(): array
    {
        return $this->getRecord()->espc->map(fn ($e) => ['espc_id' => $e->id, 'distance_km' => $e->pivot->distance_km])->values()->all();
    }

    protected function saveEtapes(array $etapes): void
    {
        $circuit = $this->getRecord();
        $sync = [];
        foreach (array_values($etapes) as $i => $etape) {
            if (empty($etape['espc_id'])) {
                continue;
            }
            // Un site n'est accepté que s'il appartient au district du circuit.
            if (! Espc::where('district_id', $circuit->district_id)->whereKey($etape['espc_id'])->exists()) {
                continue;
            }
            $sync[$etape['espc_id']] = ['ordre' => $i + 1, 'distance_km' => ($etape['distance_km'] ?? '') === '' ? null : $etape['distance_km']];
        }
        $circuit->espc()->sync($sync);

        // Distance totale non saisie : somme des étapes.
        if (($circuit->distance_totale === null || (float) $circuit->distance_totale === 0.0) && ($sum = collect($sync)->sum(fn ($s) => (float) ($s['distance_km'] ?? 0))) > 0) {
            $circuit->update(['distance_totale' => $sum]);
        }
    }
}
