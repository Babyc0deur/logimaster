<?php

namespace App\Domain\Indicators;

use App\Models\Immobilisation;
use App\Models\LivraisonEspc;
use App\Models\SortieVehicule;

/**
 * Mise en forme du détail d'un indicateur (chiffres clés + tableaux) à partir du résumé agrégé :
 * partagée par l'écran « Indicateurs », le dashboard et les rapports PDF/Excel.
 *
 * @phpstan-type Block array{kpis: array<int, array{label: string, value: string}>, tables: array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, string|int|float|null>>}>}
 */
final class IndicatorDetails
{
    /**
     * @param  array{value: float, breakdown: array}  $row  ligne de IndicatorService::summary()
     * @param  array{value: float, breakdown: array}|null  $cost  ligne « cout_global » (coût du carburant au km, pour les écarts de circuit)
     * @return array{kpis: array<int, array{label: string, value: string}>, tables: array<int, array<string, mixed>>}
     */
    public static function blocks(string $key, array $row, ?array $cost = null): array
    {
        $b = $row['breakdown'] ?? [];
        $kpis = [];
        $tables = [];
        $pct = fn ($n, $d) => $d > 0 ? number_format($n / $d * 100, 1, ',', ' ').' %' : '—';
        $num = fn ($n, $dec = 0) => number_format((float) $n, $dec, ',', ' ');
        $motif = fn ($k) => SortieVehicule::MOTIFS[$k] ?? ($k === 'non_affecte' ? 'Non affecté' : $k);
        $ratio = fn () => $num($b['numerator'] ?? 0).' / '.$num($b['denominator'] ?? 0);

        switch ($key) {
            case 'distance_totale':
                $total = (float) $row['value'];
                $kpis = [['label' => 'Distance totale', 'value' => $num($total).' km'], ['label' => 'Sorties clôturées', 'value' => $num($b['nb_sorties'] ?? 0)]];
                $tables[] = ['title' => 'Par motif de déplacement', 'headers' => ['Motif', 'Km', 'Part'],
                    'rows' => collect($b['par_motif'] ?? [])->sortDesc()->map(fn ($v, $k) => [$motif($k), $num($v), $pct($v, $total)])->values()->all()];
                $tables[] = ['title' => 'Par véhicule', 'headers' => ['Véhicule', 'Km', 'Part'],
                    'rows' => collect($b['par_vehicule'] ?? [])->sortDesc()->map(fn ($v, $k) => [$k, $num($v), $pct($v, $total)])->values()->all()];
                break;

            case 'respect_chronogramme':
                $kpis = [['label' => 'Sites livrés selon planning', 'value' => $ratio()], ['label' => 'Non livrés / en retard', 'value' => (string) count($b['non_livres'] ?? [])],
                    ['label' => 'Source', 'value' => ($b['source'] ?? 'sites') === 'sites' ? 'Chronogramme saisi' : 'Fréquence des circuits (aucun planning saisi)']];
                $tables[] = ['title' => 'Par circuit', 'headers' => ['Circuit', 'Planifiés', 'Livrés', 'Taux'],
                    'rows' => collect($b['par_circuit'] ?? [])->map(fn ($v, $k) => [$k, $v['planifie'] ?? 0, $v['livre'] ?? 0, $pct($v['livre'] ?? 0, $v['planifie'] ?? 0)])->values()->all()];
                $tables[] = ['title' => 'Sites non livrés selon le planning', 'headers' => ['Site', 'Circuit', 'Date prévue', 'Situation', 'Raison'],
                    'rows' => collect($b['non_livres'] ?? [])->map(fn ($n) => [$n['site'], $n['circuit'], $n['date'], ['en_retard' => 'Livré en retard', 'non_livre' => 'Non livré', 'planifie' => 'Non livré', 'planifiee' => 'Non réalisée', 'reportee' => 'Reportée'][$n['statut']] ?? $n['statut'], $n['raison'] ?? '—'])->all()];
                break;

            case 'taux_immobilisation':
                $kpis = [['label' => "Jours d'indisponibilité", 'value' => $ratio().' jours-véhicule'], ['label' => 'Véhicules', 'value' => $num($b['nb_vehicules'] ?? 0)]];
                $tables[] = ['title' => 'Par véhicule', 'headers' => ['Véhicule', 'Jours immobilisé', 'Taux'],
                    'rows' => collect($b['par_vehicule'] ?? [])->sortByDesc('jours')->map(fn ($v, $k) => [$k, $v['jours'], $pct($v['jours'], $v['jours_total'])])->values()->all()];
                $total = array_sum($b['jours_par_motif'] ?? []);
                $tables[] = ['title' => 'Par cause', 'headers' => ['Cause', 'Jours', 'Part'],
                    'rows' => collect($b['jours_par_motif'] ?? [])->sortDesc()->map(fn ($v, $k) => [Immobilisation::MOTIFS[$k] ?? $k, $v, $pct($v, $total)])->values()->all()];
                break;

            case 'utilisation_vehicules':
                $kpis = [['label' => "Jours d'utilisation / disponibilité", 'value' => $ratio()]];
                $perVehicle = collect($b['par_vehicule'] ?? []);
                $tables[] = ['title' => 'Par véhicule', 'headers' => ['Véhicule', 'Jours utilisés', 'Jours disponibles', 'Taux', ''],
                    'rows' => $perVehicle->map(fn ($v, $k) => [$k, $v['jours_utilises'], $v['jours_disponibles'], $pct($v['jours_utilises'], $v['jours_disponibles']),
                        ($v['jours_disponibles'] > 0 && $v['jours_utilises'] / $v['jours_disponibles'] < 0.6) ? '⚠ sous-utilisé (< 60 %)' : ''])->values()->all()];
                break;

            case 'cout_global':
                $total = (float) $row['value'];
                $km = (float) ($b['km'] ?? 0);
                $kpis = [['label' => 'Coût global', 'value' => $num($total).' FCFA'], ['label' => 'Coût au km', 'value' => $km > 0 ? $num($total / $km).' FCFA/km' : '—']];
                $tables[] = ['title' => 'Répartition', 'headers' => ['Poste', 'Montant (FCFA)', 'Part'], 'rows' => [
                    ['Carburant', $num($b['carburant'] ?? 0), $pct($b['carburant'] ?? 0, $total)],
                    ['Maintenance (vidanges + immobilisations)', $num($b['maintenance'] ?? 0), $pct($b['maintenance'] ?? 0, $total)],
                    ['Autres frais', $num($b['autres_depenses'] ?? 0), $pct($b['autres_depenses'] ?? 0, $total)],
                ]];
                $tables[] = ['title' => 'Par véhicule', 'headers' => ['Véhicule', 'Montant (FCFA)', 'Part'],
                    'rows' => collect($b['par_vehicule'] ?? [])->sortDesc()->map(fn ($v, $k) => [$k, $num($v), $pct($v, $total)])->values()->all()];
                break;

            case 'utilisation_rationnelle_carburant':
                $theo = (float) ($b['numerator'] ?? 0);
                $reel = (float) ($b['denominator'] ?? 0);
                $kpis = [['label' => 'Carburant justifié (théorique)', 'value' => $num($theo, 1).' L'], ['label' => 'Carburant acheté', 'value' => $num($reel, 1).' L'],
                    ['label' => 'Hors norme', 'value' => $num(max(0, $reel - $theo), 1).' L']];
                $tables[] = ['title' => 'Par véhicule', 'headers' => ['Véhicule', 'Théorique (L)', 'Acheté (L)', 'Écart'],
                    'rows' => collect($b['par_vehicule'] ?? [])->map(fn ($v, $k) => [$k, $num($v['theorique'], 1), $num($v['reel'], 1),
                        $v['theorique'] > 0 ? sprintf('%+.1f %%', ($v['reel'] - $v['theorique']) / $v['theorique'] * 100) : '—'])->values()->all()];
                break;

            case 'carburant_par_motif':
                $total = (float) $row['value'];
                $kpis = [['label' => 'Carburant total', 'value' => $num($total, 1).' L']];
                $tables[] = ['title' => 'Par motif', 'headers' => ['Motif', 'Litres', 'Part'],
                    'rows' => collect($b['par_motif'] ?? [])->sortDesc()->map(fn ($v, $k) => [$motif($k), $num($v, 1), $pct($v, $total)])->values()->all()];
                break;

            case 'respect_circuits':
                $extra = (float) ($b['km_supplementaires'] ?? 0);
                $fuelPerKm = ($cost && ($cost['breakdown']['km'] ?? 0) > 0) ? ($cost['breakdown']['carburant'] ?? 0) / $cost['breakdown']['km'] : null;
                $kpis = [['label' => 'Circuits respectés', 'value' => $ratio()], ['label' => 'Distance supplémentaire', 'value' => $num($extra, 1).' km'],
                    ['label' => 'Coût supplémentaire estimé', 'value' => $fuelPerKm !== null ? $num($extra * $fuelPerKm).' FCFA' : '—']];
                $tables[] = ['title' => 'Écarts constatés', 'headers' => ['Circuit', 'Date', 'Véhicule', 'Km en plus', 'Raison'],
                    'rows' => collect($b['ecarts'] ?? [])->map(fn ($e) => [$e['circuit'], $e['date'], $e['vehicule'], $e['km_supplementaires'] !== null ? '+'.$num($e['km_supplementaires'], 1) : '—', $e['raison'] ?? '—'])->all()];
                break;

            case 'respect_espc':
                $kpis = [['label' => 'Livraisons conformes (sur site)', 'value' => $ratio()]];
                $delais = $b['delais'] ?? [];
                $totalDelais = array_sum($delais);
                $tables[] = ['title' => 'Délais de livraison', 'headers' => ['Délai', 'Livraisons', 'Part'], 'rows' => [
                    ['Dans les délais', $delais['dans_les_delais'] ?? 0, $pct($delais['dans_les_delais'] ?? 0, $totalDelais)],
                    ['Retard ≤ 24 h', $delais['retard_24h'] ?? 0, $pct($delais['retard_24h'] ?? 0, $totalDelais)],
                    ['Retard > 24 h', $delais['retard_plus_24h'] ?? 0, $pct($delais['retard_plus_24h'] ?? 0, $totalDelais)],
                ]];
                $tables[] = ['title' => 'Livraisons non conformes', 'headers' => ['Site', 'Prévu le', 'Réalisé', 'Raison'],
                    'rows' => collect($b['non_conformes'] ?? [])->map(fn ($n) => [$n['site'], $n['date_prevue'], $n['realise'], $n['raison'] ?? '—'])->all()];
                break;
        }

        return ['kpis' => $kpis, 'tables' => $tables];
    }
}
