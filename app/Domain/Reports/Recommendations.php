<?php

namespace App\Domain\Reports;

use App\Domain\Indicators\IndicatorCatalog;

/** Commentaires et recommandations générés à partir des indicateurs du mois (écarts aux objectifs, causes dominantes). */
final class Recommendations
{
    /**
     * @param  array<string, array{value: float, breakdown: array, districts: int, previous: ?float, delta_pct: ?float}>  $rows
     * @return array<int, string>
     */
    public static function for(array $rows): array
    {
        $out = [];
        $off = fn (string $key) => $rows[$key]['districts'] > 0 && ! in_array(IndicatorCatalog::rowColor($key, $rows[$key]), ['success', 'gray'], true);
        $fmt = fn (string $key) => IndicatorCatalog::format($key, $rows[$key]['value']);
        $target = fn (string $key) => IndicatorCatalog::format($key, IndicatorCatalog::get($key)['target']);

        if ($off('respect_chronogramme')) {
            $missed = $rows['respect_chronogramme']['breakdown']['non_livres'] ?? [];
            $reasons = collect($missed)->pluck('raison')->filter()->countBy()->sortDesc()->keys()->take(3)->join(', ');
            $out[] = "Chronogramme : {$fmt('respect_chronogramme')} (objectif {$target('respect_chronogramme')}). ".count($missed).' site(s) non livré(s) selon le planning'
                .($reasons ? " — causes principales : {$reasons}" : '').'. Sécuriser les circuits concernés et anticiper les reports.';
        }
        if ($off('taux_immobilisation')) {
            $worst = collect($rows['taux_immobilisation']['breakdown']['par_vehicule'] ?? [])->sortByDesc('jours')->filter(fn ($v) => $v['jours'] > 0)->take(3)
                ->map(fn ($v, $k) => "{$k} ({$v['jours']} j)")->join(', ');
            $out[] = "Immobilisation : {$fmt('taux_immobilisation')} (objectif {$target('taux_immobilisation')})".($worst ? " — véhicules les plus touchés : {$worst}" : '')
                .'. Renforcer l\'entretien préventif et prévoir un véhicule de remplacement.';
        }
        if ($off('utilisation_vehicules')) {
            $idle = collect($rows['utilisation_vehicules']['breakdown']['par_vehicule'] ?? [])->filter(fn ($v) => $v['jours_disponibles'] > 0 && $v['jours_utilises'] / $v['jours_disponibles'] < 0.6)->keys()->take(5)->join(', ');
            $out[] = "Utilisation : {$fmt('utilisation_vehicules')} (objectif {$target('utilisation_vehicules')})".($idle ? " — véhicules sous-utilisés : {$idle}" : '').'. Réaffecter ou mutualiser les véhicules peu utilisés.';
        }
        if ($off('utilisation_rationnelle_carburant')) {
            $over = collect($rows['utilisation_rationnelle_carburant']['breakdown']['par_vehicule'] ?? [])->filter(fn ($v) => $v['theorique'] > 0 && $v['reel'] > $v['theorique'] * 1.15)->keys()->take(5)->join(', ');
            $out[] = "Carburant : utilisation rationnelle à {$fmt('utilisation_rationnelle_carburant')} (objectif {$target('utilisation_rationnelle_carburant')})".($over ? " — surconsommation constatée : {$over}" : '').'. Contrôler les pleins, les trajets non planifiés et l\'état mécanique.';
        }
        $fuel = $rows['utilisation_rationnelle_carburant'];
        if ($fuel['districts'] > 0 && ! ($fuel['evaluated'] ?? true)) {
            $out[] = 'Carburant : utilisation rationnelle non évaluée. '.IndicatorCatalog::notEvaluatedReason('utilisation_rationnelle_carburant', $fuel['breakdown'])
                .' Renseigner la consommation théorique sur la fiche de chaque véhicule.';
        }
        if ($off('respect_circuits')) {
            $km = $rows['respect_circuits']['breakdown']['km_supplementaires'] ?? 0;
            $out[] = "Circuits : {$fmt('respect_circuits')} respectés (objectif {$target('respect_circuits')}), {$km} km supplémentaires parcourus. Documenter les déviations et réviser les circuits à risque.";
        }
        $cost = $rows['cout_global'];
        if ($cost['districts'] > 0 && ($cost['delta_pct'] ?? 0) > 10) {
            $out[] = sprintf('Coût global : %s, en hausse de %.1f %% par rapport au mois précédent. Analyser la répartition carburant / maintenance / frais.', $fmt('cout_global'), $cost['delta_pct']);
        }

        return $out ?: ['Tous les indicateurs suivis sont conformes aux objectifs sur la période. Poursuivre les bonnes pratiques.'];
    }
}
