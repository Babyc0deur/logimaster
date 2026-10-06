<?php

namespace App\Domain\Fleet;

use App\Domain\Indicators\ImmobilisationDays;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Analyses du tableau de bord, par véhicule et par motif de déplacement, sur la même période et le même périmètre que les
 * indicateurs DDKM :
 *   - taux d'utilisation de chaque véhicule (jours utilisés / jours disponibles, hors immobilisation) ;
 *   - répartition de l'utilisation par motif et par véhicule (jours-véhicule par motif) ;
 *   - distance parcourue par motif et par véhicule (km) ;
 *   - carburant consommé par motif et par véhicule (litres) ;
 *   - utilisation rationnelle du carburant par véhicule (consommation théorique / réelle, 100 % = conforme).
 */
class FleetAnalytics
{
    /**
     * @param  array<int, string>  $districtIds
     * @return array{motifs: array<string, string>, utilisation: array, repartition: array, distance: array, carburant: array, rationnel: array}
     */
    public function compute(array $districtIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $vehicles = Vehicle::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('district_id', $districtIds)->get(['id', 'district_id', 'immatriculation', 'consommation_theorique'])->keyBy('id');
        $label = fn (string $id) => $vehicles[$id]->immatriculation ?? '?';
        $motifLabel = fn (?string $m) => SortieVehicule::MOTIFS[$m ?? 'autre'] ?? ($m ? ucfirst(str_replace('_', ' ', $m)) : 'Non affecté');

        $sorties = SortieVehicule::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('district_id', $districtIds)->where('statut', '!=', 'annulee')
            ->whereBetween('date_sortie', [$from->toDateString(), $to->toDateString()])->get(['id', 'vehicle_id', 'motif', 'date_sortie', 'km_depart', 'km_arrivee']);
        $fuel = Ravitaillement::withoutGlobalScopes()->whereNull('deleted_at')->with('sortie:id,motif')->whereIn('district_id', $districtIds)
            ->whereBetween('date_ravitaillement', [$from->toDateString(), $to->toDateString()])->get(['id', 'vehicle_id', 'sortie_id', 'motif', 'litres']);

        // ---- utilisation : jours utilisés / jours disponibles (immobilisations déduites)
        $immo = [];
        foreach (array_unique($vehicles->pluck('district_id')->all()) as $districtId) {
            $immo += ImmobilisationDays::compute($districtId, $from, $to)['per_vehicle'];
        }
        $usedDays = $sorties->groupBy('vehicle_id')->map(fn ($g) => $g->map(fn ($s) => $s->date_sortie->toDateString())->unique()->count());
        $utilisation = [];
        foreach ($vehicles as $id => $v) {
            $available = max(0, $days - ($immo[$id] ?? 0));
            $used = min((int) ($usedDays[$id] ?? 0), max($available, (int) ($usedDays[$id] ?? 0)));
            if ($used > 0 || $available > 0) {
                $utilisation[$v->immatriculation] = ['jours' => $used, 'disponibles' => $available, 'taux' => $available > 0 ? round($used / $available * 100, 1) : 0.0];
            }
        }
        uasort($utilisation, fn ($a, $b) => $b['taux'] <=> $a['taux']);

        // ---- répartitions par véhicule × motif
        $repartition = [];   // jours-véhicule par motif (un véhicule utilisé un jour pour un motif = 1)
        foreach ($sorties->groupBy(fn ($s) => $s->vehicle_id.'|'.$s->motif) as $key => $g) {
            [$vid, $m] = explode('|', $key, 2);
            $repartition[$label($vid)][$motifLabel($m ?: null)] = $g->map(fn ($s) => $s->date_sortie->toDateString())->unique()->count();
        }
        $distance = [];
        foreach ($sorties as $s) {
            if ($s->km_arrivee !== null && $s->km_depart !== null && $s->km_arrivee >= $s->km_depart) {
                $distance[$label($s->vehicle_id)][$motifLabel($s->motif)] = ($distance[$label($s->vehicle_id)][$motifLabel($s->motif)] ?? 0) + ($s->km_arrivee - $s->km_depart);
            }
        }
        $carburant = [];
        foreach ($fuel as $r) {
            $m = $motifLabel($r->motif ?? $r->sortie?->motif ?? 'non_affecte');
            $carburant[$label($r->vehicle_id)][$m] = round(($carburant[$label($r->vehicle_id)][$m] ?? 0) + (float) $r->litres, 1);
        }

        // ---- carburant rationnel : théorique (km × conso du véhicule) / réel (litres pris)
        $theo = [];
        foreach ($sorties as $s) {
            $v = $vehicles[$s->vehicle_id] ?? null;
            if ($v?->consommation_theorique && $s->km_arrivee !== null && $s->km_arrivee >= $s->km_depart) {
                $theo[$v->immatriculation] = ($theo[$v->immatriculation] ?? 0) + ($s->km_arrivee - $s->km_depart) * (float) $v->consommation_theorique / 100;
            }
        }
        $reel = [];
        foreach ($fuel as $r) {
            $reel[$label($r->vehicle_id)] = ($reel[$label($r->vehicle_id)] ?? 0) + (float) $r->litres;
        }
        $rationnel = [];
        foreach (array_unique([...array_keys($theo), ...array_keys($reel)]) as $immat) {
            $t = round($theo[$immat] ?? 0, 1);
            $l = round($reel[$immat] ?? 0, 1);
            // non évalué (taux null) sans plein déclaré ou sans consommation théorique renseignée pour le véhicule
            $rationnel[$immat] = ['theorique' => $t, 'reel' => $l, 'taux' => $l > 0 && $t > 0 ? round($t / $l * 100, 1) : null];
        }
        uasort($rationnel, fn ($a, $b) => ($a['taux'] ?? 999) <=> ($b['taux'] ?? 999));

        $byTotal = function (array $matrix) {
            uasort($matrix, fn ($a, $b) => array_sum($b) <=> array_sum($a));

            return $matrix;
        };
        $motifs = collect([$repartition, $distance, $carburant])->flatMap(fn ($m) => collect($m)->flatMap(fn ($row) => array_keys($row)))->unique()->values()->all();

        return [
            'motifs' => $motifs,
            'utilisation' => $utilisation,
            'repartition' => $byTotal($repartition),
            'distance' => $byTotal($distance),
            'carburant' => $byTotal($carburant),
            'rationnel' => $rationnel,
        ];
    }
}
