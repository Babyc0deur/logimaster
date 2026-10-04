<?php

namespace App\Domain\Indicators\Calculators;

use App\Domain\Indicators\Deliveries;
use App\Domain\Indicators\IndicatorCalculator;
use App\Domain\Indicators\IndicatorResult;
use App\Models\Circuit;
use App\Models\SortieVehicule;
use Carbon\CarbonImmutable;

/**
 * Sites livrés selon le planning / sites planifiés au chronogramme (ex. « 37/40 »).
 * Un site compte comme livré « selon planning » s'il est livré au plus tard à la date prévue.
 * Une sortie planifiée sans liste de sites compte pour une unité (réalisée ou non).
 * Sans aucun planning saisi, repli sur la fréquence des circuits actifs.
 */
class RespectChronogrammeCalculator implements IndicatorCalculator
{
    public function key(): string
    {
        return 'respect_chronogramme';
    }

    public function isRatio(): bool
    {
        return true;
    }

    public function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): IndicatorResult
    {
        $plans = Deliveries::plans($districtId, $start, $end);
        if ($plans->isNotEmpty()) {
            $planned = $done = 0;
            $missed = [];
            $perCircuit = [];

            foreach ($plans as $plan) {
                $circuit = $plan->circuit?->nom ?? 'Sans circuit';
                if ($plan->livraisons->isEmpty()) {
                    $planned++;
                    $perCircuit[$circuit]['planifie'] = ($perCircuit[$circuit]['planifie'] ?? 0) + 1;
                    if ($plan->statut === 'realisee') {
                        $done++;
                        $perCircuit[$circuit]['livre'] = ($perCircuit[$circuit]['livre'] ?? 0) + 1;
                    } else {
                        $missed[] = ['site' => $plan->destination ?? $circuit, 'circuit' => $circuit, 'date' => $plan->date_prevue->toDateString(), 'statut' => $plan->statut, 'raison' => $plan->raison];
                    }

                    continue;
                }
                foreach ($plan->livraisons as $l) {
                    $planned++;
                    $perCircuit[$circuit]['planifie'] = ($perCircuit[$circuit]['planifie'] ?? 0) + 1;
                    if (Deliveries::onSchedule($l, $plan)) {
                        $done++;
                        $perCircuit[$circuit]['livre'] = ($perCircuit[$circuit]['livre'] ?? 0) + 1;
                    } else {
                        $missed[] = [
                            'site' => $l->espc?->nom, 'circuit' => $circuit, 'date' => $plan->date_prevue->toDateString(),
                            'statut' => $l->statut === 'livre' ? 'en_retard' : $l->statut, 'raison' => $l->raison_non_livraison ?? $plan->raison,
                        ];
                    }
                }
            }

            return IndicatorResult::ratio($done, $planned, ['source' => 'sites', 'non_livres' => $missed, 'par_circuit' => $perCircuit]);
        }

        // Repli : aucun planning saisi, on déduit l'attendu de la fréquence des circuits actifs.
        $days = (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        $done = SortieVehicule::query()
            ->where('district_id', $districtId)
            ->whereNotNull('circuit_id')
            ->where('statut', '!=', 'annulee')
            ->whereDateBetween('date_sortie', $start, $end)
            ->selectRaw('circuit_id, count(*) as n')->groupBy('circuit_id')
            ->pluck('n', 'circuit_id');

        $expected = 0;
        $realised = 0;
        $detail = [];
        foreach (Circuit::where('district_id', $districtId)->where('statut', 'actif')->get() as $circuit) {
            $exp = $this->expectedRuns($circuit->frequence, $days);
            if ($exp === 0) {
                continue;
            }
            $real = min((int) ($done[$circuit->id] ?? 0), $exp);
            $expected += $exp;
            $realised += $real;
            $detail[$circuit->nom] = ['planifie' => $exp, 'livre' => $real];
        }

        return IndicatorResult::ratio($realised, $expected, ['source' => 'frequence', 'par_circuit' => $detail, 'non_livres' => []]);
    }

    private function expectedRuns(?string $frequence, int $days): int
    {
        return match (strtolower((string) $frequence)) {
            'quotidien' => $days,
            'hebdomadaire' => (int) ceil($days / 7),
            'bimensuel', 'quinzaine' => (int) ceil($days / 15),
            'mensuel' => 1,
            'trimestriel' => $days >= 90 ? 1 : 0,
            default => 0,
        };
    }
}
