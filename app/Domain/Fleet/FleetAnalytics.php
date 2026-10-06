<?php

namespace App\Domain\Fleet;

use App\Domain\Indicators\ImmobilisationDays;
use App\Domain\Reports\ChartSvg;
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

    /**
     * Les cinq graphiques (SVG) des analyses, identiques sur le tableau de bord et dans le rapport DDKM.
     *
     * @return array<int, array{title: string, help: string, svg: ?string, more: int, note?: ?string}>
     */
    public static function panels(array $a, int $limit = 12): array
    {
        $motifs = $a['motifs'];
        $top = fn (array $rows) => array_slice($rows, 0, $limit, true);
        $more = fn (array $rows) => max(0, count($rows) - $limit);
        $ok = '#22c55e';
        $warn = '#f59e0b';
        $bad = '#ef4444';

        $util = $top($a['utilisation']);
        $rat = $top(array_filter($a['rationnel'], fn ($r) => $r['taux'] !== null));

        $panels = [
            [
                'title' => 'Taux d\'utilisation de chaque véhicule',
                'help' => 'Jours avec au moins une sortie / jours disponibles (immobilisations déduites). Objectif : 60 %.',
                'svg' => $util ? ChartSvg::hbars(array_keys($util), array_column($util, 'taux'), '%',
                    array_map(fn ($r) => $r['taux'] >= 60 ? $ok : ($r['taux'] >= 30 ? $warn : $bad), array_values($util)), 60) : null,
                'more' => $more($a['utilisation']),
            ],
            [
                'title' => 'Répartition de l\'utilisation par motif et par véhicule',
                'help' => 'Jours d\'utilisation de chaque véhicule, ventilés par motif de déplacement.',
                'svg' => $a['repartition'] ? ChartSvg::hstack($top($a['repartition']), $motifs, 'j') : null,
                'more' => $more($a['repartition']),
            ],
            [
                'title' => 'Distance parcourue par motif et par véhicule',
                'help' => 'Kilomètres des sorties (compteur de retour − départ), ventilés par motif.',
                'svg' => $a['distance'] ? ChartSvg::hstack($top($a['distance']), $motifs, 'km') : null,
                'more' => $more($a['distance']),
            ],
            [
                'title' => 'Carburant consommé par motif et par véhicule',
                'help' => 'Litres des pleins, rattachés au motif de la sortie.',
                'svg' => $a['carburant'] ? ChartSvg::hstack($top($a['carburant']), $motifs, 'L') : null,
                'more' => $more($a['carburant']),
            ],
            [
                'title' => 'Utilisation rationnelle du carburant par véhicule',
                'help' => 'Consommation théorique (km × consommation du véhicule) / litres réellement pris. 100 % = conforme ; en dessous = surconsommation. Objectif : 85 %.',
                'svg' => $rat ? ChartSvg::hbars(array_keys($rat), array_map(fn ($r) => min(200, $r['taux']), array_values($rat)), '%',
                    array_map(fn ($r) => $r['taux'] >= 85 ? $ok : ($r['taux'] >= 70 ? $warn : $bad), array_values($rat)), 85) : null,
                'more' => $more(array_filter($a['rationnel'], fn ($r) => $r['taux'] !== null)),
                'note' => ($n = count(array_filter($a['rationnel'], fn ($r) => $r['taux'] === null))) ? "{$n} véhicule(s) non évalué(s) : aucun plein déclaré sur la période, ou consommation théorique (L/100 km) non renseignée sur la fiche du véhicule." : null,
            ],
        ];

        return $panels;
    }
}
