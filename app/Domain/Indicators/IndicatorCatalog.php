<?php

namespace App\Domain\Indicators;

/** Libellés, unités, objectifs et définitions des 9 indicateurs DDKM (écrans, rapports). */
final class IndicatorCatalog
{
    /**
     * direction : 'up' = plus c'est haut mieux c'est ; 'down' = plus c'est bas mieux c'est ; null = informatif.
     * target : seuil de conformité utilisé pour la couleur (null = pas d'objectif).
     *
     * @return array<string, array{label: string, short: string, unit: string, direction: ?string, target: ?float, definition: string, icon: string}>
     */
    public static function defaults(): array
    {
        // ordre d'affichage du tableau de bord ; la livraison ESPC sur site n'est plus affichée (même information que le respect du chronogramme)
        return [
            'cout_global' => [
                'label' => 'Coût global de prise en charge', 'short' => 'Coût global', 'unit' => 'FCFA', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-banknotes',
                'definition' => 'Carburant + maintenance (vidanges et immobilisations) + autres frais. Coût au km = coût global ÷ distance parcourue.',
            ],
            'utilisation_vehicules' => [
                'label' => "Taux d'utilisation des véhicules", 'short' => "Taux d'utilisation", 'unit' => '%', 'direction' => 'up', 'target' => 60.0, 'icon' => 'heroicon-o-truck',
                'definition' => 'Jours d\'utilisation ÷ jours de disponibilité (jours de la période moins jours d\'immobilisation). Un véhicule est utilisé un jour s\'il a au moins une sortie.',
            ],
            'taux_immobilisation' => [
                'label' => "Taux d'immobilisation", 'short' => "Taux d'immobilisation", 'unit' => '%', 'direction' => 'down', 'target' => 10.0, 'icon' => 'heroicon-o-wrench-screwdriver',
                'definition' => "Jours d'indisponibilité ÷ (nombre de véhicules × jours de la période), par véhicule et par cause.",
            ],
            'respect_chronogramme' => [
                'label' => 'Taux de respect du chronogramme', 'short' => 'Taux de respect du chronogramme', 'unit' => '%', 'direction' => 'up', 'target' => 90.0, 'icon' => 'heroicon-o-calendar-days',
                'definition' => 'Sites livrés selon le planning ÷ sites planifiés au chronogramme. Un site est « selon planning » s\'il est livré au plus tard à la date prévue.',
            ],
            'utilisation_rationnelle_carburant' => [
                'label' => 'Utilisation rationnelle du carburant', 'short' => "Taux d'utilisation rationnelle du carburant", 'unit' => '%', 'direction' => 'up', 'target' => 85.0, 'icon' => 'heroicon-o-fire',
                'definition' => 'Carburant théorique (km × consommation théorique ÷ 100) ÷ carburant réellement acheté. 100 % = consommation conforme.',
            ],
            'carburant_par_motif' => [
                'label' => 'Carburant par motif de déplacement', 'short' => 'Carburant par motif', 'unit' => 'L', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-beaker',
                'definition' => 'Litres consommés, ventilés par motif (celui du ravitaillement, sinon celui de la sortie liée).',
            ],
            'respect_circuits' => [
                'label' => 'Taux de respect des circuits', 'short' => 'Circuit', 'unit' => '%', 'direction' => 'up', 'target' => 90.0, 'icon' => 'heroicon-o-map-pin',
                'definition' => 'Sorties dont le circuit a été respecté ÷ sorties sur circuit évaluées. Écarts : circuit, date, raison, km supplémentaires.',
            ],
            'distance_totale' => [
                'label' => 'Distance totale parcourue', 'short' => 'Distance totale parcourue', 'unit' => 'km', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-map',
                'definition' => 'Somme des kilomètres des sorties clôturées de la période, ventilée par motif de déplacement et par véhicule.',
            ],
        ];
    }

    /** Catalogue avec les objectifs personnalisés (Paramètres → Objectifs des indicateurs) par-dessus les valeurs par défaut. */
    public static function all(): array
    {
        $rows = self::defaults();
        foreach ($rows as $key => &$meta) {
            if ($meta['direction'] !== null && is_numeric($custom = \App\Models\Setting::get("objectif_{$key}"))) {
                $meta['target'] = (float) $custom;
            }
        }

        return $rows;
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Indicateur inconnu : {$key}");
    }

    /** Couleur Filament selon la valeur et l'objectif : success | warning | danger | gray. */
    public static function color(string $key, ?float $value): string
    {
        $meta = self::get($key);
        if ($value === null || $meta['direction'] === null || $meta['target'] === null) {
            return 'gray';
        }
        $ok = $meta['direction'] === 'up' ? $value >= $meta['target'] : $value <= $meta['target'];
        $near = $meta['direction'] === 'up' ? $value >= $meta['target'] * 0.85 : $value <= $meta['target'] * 1.5;

        return $ok ? 'success' : ($near ? 'warning' : 'danger');
    }

    public const NON_EVALUE = 'Non évalué';

    /**
     * Faux quand l'indicateur ne peut pas être calculé faute de données de référence. Carburant rationnel :
     * aucun véhicule avec une consommation théorique (L/100 km) n'a roulé et fait le plein sur la période.
     */
    public static function evaluated(string $key, array $breakdown): bool
    {
        if ($key === 'utilisation_rationnelle_carburant') {
            return (float) ($breakdown['numerator'] ?? 0) > 0 && (float) ($breakdown['denominator'] ?? 0) > 0;
        }

        return true;
    }

    /** Pourquoi l'indicateur n'est pas évalué (texte pour l'utilisateur). */
    public static function notEvaluatedReason(string $key, array $breakdown): string
    {
        $sans = array_column($breakdown['sans_consommation'] ?? [], 'immatriculation');

        return 'Consommation théorique (L/100 km) non renseignée'.($sans ? ' pour '.implode(', ', array_slice($sans, 0, 5)) : ' sur les fiches des véhicules')
            .', ou aucun plein déclaré sur la période.';
    }

    /** Valeur affichée d'une ligne de synthèse ({value, breakdown, evaluated}). */
    public static function display(string $key, array $row): string
    {
        return ($row['evaluated'] ?? true) ? self::format($key, (float) $row['value']) : self::NON_EVALUE;
    }

    /** Couleur d'une ligne de synthèse : grise quand l'indicateur n'est pas évalué. */
    public static function rowColor(string $key, array $row): string
    {
        return ($row['evaluated'] ?? true) ? self::color($key, (float) $row['value']) : 'gray';
    }

    public static function format(string $key, float $value): string
    {
        $unit = self::get($key)['unit'];

        return match ($unit) {
            '%' => number_format($value, 1, ',', ' ').' %',
            'FCFA' => number_format($value, 0, ',', ' ').' FCFA',
            default => number_format($value, 0, ',', ' ').' '.$unit,
        };
    }
}
