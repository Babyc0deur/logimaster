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
        return [
            'distance_totale' => [
                'label' => 'Distance totale parcourue', 'short' => 'Distance', 'unit' => 'km', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-map',
                'definition' => 'Somme des kilomètres des sorties clôturées de la période, ventilée par motif de déplacement et par véhicule.',
            ],
            'respect_chronogramme' => [
                'label' => 'Taux de respect du chronogramme', 'short' => 'Chronogramme', 'unit' => '%', 'direction' => 'up', 'target' => 90.0, 'icon' => 'heroicon-o-calendar-days',
                'definition' => 'Sites livrés selon le planning ÷ sites planifiés au chronogramme. Un site est « selon planning » s\'il est livré au plus tard à la date prévue.',
            ],
            'taux_immobilisation' => [
                'label' => "Taux d'immobilisation", 'short' => 'Immobilisation', 'unit' => '%', 'direction' => 'down', 'target' => 10.0, 'icon' => 'heroicon-o-wrench-screwdriver',
                'definition' => "Jours d'indisponibilité ÷ (nombre de véhicules × jours de la période), par véhicule et par cause.",
            ],
            'utilisation_vehicules' => [
                'label' => "Taux d'utilisation des véhicules", 'short' => 'Utilisation', 'unit' => '%', 'direction' => 'up', 'target' => 60.0, 'icon' => 'heroicon-o-truck',
                'definition' => 'Jours d\'utilisation ÷ jours de disponibilité (jours de la période moins jours d\'immobilisation). Un véhicule est utilisé un jour s\'il a au moins une sortie.',
            ],
            'cout_global' => [
                'label' => 'Coût global de prise en charge', 'short' => 'Coût global', 'unit' => 'FCFA', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-banknotes',
                'definition' => 'Carburant + maintenance (vidanges et immobilisations) + autres frais. Coût au km = coût global ÷ distance parcourue.',
            ],
            'utilisation_rationnelle_carburant' => [
                'label' => 'Utilisation rationnelle du carburant', 'short' => 'Carburant rationnel', 'unit' => '%', 'direction' => 'up', 'target' => 85.0, 'icon' => 'heroicon-o-fire',
                'definition' => 'Carburant théorique (km × consommation théorique ÷ 100) ÷ carburant réellement acheté. 100 % = consommation conforme.',
            ],
            'carburant_par_motif' => [
                'label' => 'Carburant par motif de déplacement', 'short' => 'Carburant / motif', 'unit' => 'L', 'direction' => null, 'target' => null, 'icon' => 'heroicon-o-beaker',
                'definition' => 'Litres consommés, ventilés par motif (celui du ravitaillement, sinon celui de la sortie liée).',
            ],
            'respect_circuits' => [
                'label' => 'Taux de respect des circuits', 'short' => 'Circuits', 'unit' => '%', 'direction' => 'up', 'target' => 90.0, 'icon' => 'heroicon-o-map-pin',
                'definition' => 'Sorties dont le circuit a été respecté ÷ sorties sur circuit évaluées. Écarts : circuit, date, raison, km supplémentaires.',
            ],
            'respect_espc' => [
                'label' => 'Taux de respect de la livraison ESPC sur site', 'short' => 'Livraison ESPC', 'unit' => '%', 'direction' => 'up', 'target' => 95.0, 'icon' => 'heroicon-o-building-office-2',
                'definition' => 'Livraisons effectuées sur site ÷ livraisons prévues. Délais : dans les délais, retard ≤ 24 h, retard > 24 h.',
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
