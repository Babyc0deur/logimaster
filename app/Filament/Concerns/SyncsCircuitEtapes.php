<?php

namespace App\Filament\Concerns;

use App\Models\Espc;

/** Lecture / enregistrement des étapes d'un circuit (table pivot ordonnée avec distance) depuis le formulaire. */
trait SyncsCircuitEtapes
{
    /** @return array<int, array{espc_id: string, distance_km: mixed}> */
    protected function loadEtapes(): array
    {
        return $this->getRecord()->espc->map(fn ($e) => ['espc_id' => $e->id, 'distance_km' => $e->pivot->distance_km, 'livre_via_espc_id' => $e->pivot->livre_via_espc_id])->values()->all();
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
            $sync[$etape['espc_id']] = ['ordre' => $i + 1, 'distance_km' => ($etape['distance_km'] ?? '') === '' ? null : $etape['distance_km'],
                'livre_via_espc_id' => ($etape['livre_via_espc_id'] ?? '') ?: null];
        }
        // « Livré via » : le point de transit doit être un autre site du même circuit.
        foreach ($sync as $id => $row) {
            if ($row['livre_via_espc_id'] === $id || ($row['livre_via_espc_id'] && ! isset($sync[$row['livre_via_espc_id']]))) {
                $sync[$id]['livre_via_espc_id'] = null;
            }
        }
        $circuit->espc()->sync($sync);

        // Distance totale non saisie : somme des étapes.
        if (($circuit->distance_totale === null || (float) $circuit->distance_totale === 0.0) && ($sum = collect($sync)->sum(fn ($s) => (float) ($s['distance_km'] ?? 0))) > 0) {
            $circuit->update(['distance_totale' => $sum]);
        }
    }
}
