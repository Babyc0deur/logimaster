<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\Deliveries;
use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Circuit;
use App\Models\SortieVehicule;
use Carbon\CarbonImmutable;

/**
 * Livraison ESPC sur site : livraisons conformes (livrées sur site) / livraisons prévues (ex. « 39/40 »).
 * Détail : livraisons non conformes (prévu sur site, réalisé en transit, ou non livré, avec la raison)
 * et délais (dans les délais / retard ≤ 24 h / retard > 24 h).
 * Sans suivi de livraison saisi, repli sur les circuits parcourus : ESPC desservis / ESPC rattachés à un circuit actif.
 */
class RespectEspcCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'respect_espc';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $plans = Deliveries::plans($districtId, $start, $end)->filter(fn ($p) => $p->livraisons->isNotEmpty());

        if ($plans->isNotEmpty()) {
            $planned = $conformes = 0;
            $delais = ['dans_les_delais' => 0, 'retard_24h' => 0, 'retard_plus_24h' => 0];
            $non = [];
            foreach ($plans as $plan) {
                foreach ($plan->livraisons as $l) {
                    $planned++;
                    if ($l->statut === 'livre') {
                        $delais[$l->delai]++;
                    }
                    if ($l->statut === 'livre' && $l->lieu_livraison !== 'transit') {
                        $conformes++;

                        continue;
                    }
                    $non[] = [
                        'site' => $l->espc?->nom, 'date_prevue' => $plan->date_prevue->toDateString(),
                        'realise' => $l->statut === 'livre' ? ($l->date_livraison?->toDateString().' — point de transit') : 'non livré',
                        'raison' => $l->raison_non_livraison ?? $plan->raison,
                    ];
                }
            }

            return IndicatorResult::ratio($conformes, $planned, ['source' => 'livraisons', 'non_conformes' => $non, 'delais' => $delais]);
        }

        // Repli : ESPC desservis par au moins une sortie du circuit (sans refus explicite) / ESPC actifs rattachés à un circuit.
        $served = SortieVehicule::where('district_id', $districtId)
            ->whereNotNull('circuit_id')->where('statut', '!=', 'annulee')
            ->where(fn ($q) => $q->whereNull('circuit_respecte')->orWhere('circuit_respecte', true))
            ->whereDateBetween('date_sortie', $start, $end)
            ->distinct()->pluck('circuit_id');

        $all = [];
        $done = [];
        foreach (Circuit::with(['espc' => fn ($q) => $q->where('espc.statut', 'actif')])
            ->where('district_id', $districtId)->get() as $circuit) {
            foreach ($circuit->espc as $espc) {
                $all[$espc->id] = true;
                if ($served->contains($circuit->id)) {
                    $done[$espc->id] = true;
                }
            }
        }

        return IndicatorResult::ratio(count($done), count($all), ['source' => 'circuits', 'non_conformes' => [], 'delais' => []]);
    }
}
