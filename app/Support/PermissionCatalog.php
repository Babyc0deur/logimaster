<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;

/**
 * Permissions regroupées par élément de l'application (Véhicules, Chronogramme, Carburant…), avec des libellés en français,
 * pour le formulaire des rôles. Toute permission inconnue tombe dans « Autres » : rien n'est jamais masqué.
 */
final class PermissionCatalog
{
    /** Éléments, dans l'ordre d'affichage : clé de module => [libellé, description]. */
    public const ITEMS = [
        'pilotage' => ['Tableau de bord et indicateurs', 'Tableau de bord, 8 indicateurs DDKM'],
        'chronogrammes' => ['Chronogramme', 'Planning des sorties et sa validation'],
        'circuits' => ['Circuits', 'Itinéraires et centres desservis'],
        'sorties' => ['Sorties véhicules', 'Missions réalisées, kilométrage'],
        'livraisons' => ['Suivi des livraisons', 'Livraisons site par site'],
        'vehicles' => ['Véhicules', 'Parc, échéances, signalements terrain'],
        'drivers' => ['Chauffeurs', 'Fiches chauffeur (permis, statistiques)'],
        'personnels' => ['Personnel', 'Chauffeurs, chefs de mission, passagers'],
        'espc' => ['Centres de santé', 'Sites livrés'],
        'ravitaillements' => ['Carburant', 'Pleins et factures de carburant'],
        'vidanges' => ['Vidanges', 'Entretiens réalisés'],
        'immobilisations' => ['Immobilisations', 'Véhicules à l\'arrêt'],
        'expenses' => ['Dépenses', 'Autres frais'],
        'budgets' => ['Budgets', 'Module Finance'],
        'factures' => ['Factures', 'Module Finance'],
        'documents' => ['Documents', 'Pièces jointes'],
        'reports' => ['Rapports', 'PDF et Excel, envois planifiés'],
        'organisation' => ['Organisation', 'PRES, régions, districts'],
        'users' => ['Utilisateurs', 'Comptes du bureau'],
        'administration' => ['Administration', 'Journal d\'audit, paramètres'],
        'mobile' => ['Application convoyeur', 'Exécution des circuits sur téléphone'],
        'autres' => ['Autres', 'Permissions non classées'],
    ];

    /** Permissions rangées ailleurs que dans le module de leur nom. */
    private const SPECIAL = [
        'view_dashboard' => ['pilotage', 'Voir le tableau de bord'],
        'view_indicators' => ['pilotage', 'Voir les indicateurs DDKM'],
        'view_districts' => ['organisation', 'Voir'],
        'manage_districts' => ['organisation', 'Gérer (créer, modifier)'],
        'view_audit_logs' => ['administration', 'Voir le journal d\'audit'],
        'manage_settings' => ['administration', 'Gérer les paramètres'],
        'execute_circuits' => ['mobile', 'Exécuter les circuits (téléphone)'],
    ];

    private const ACTIONS = [
        'view' => 'Voir', 'create' => 'Créer', 'update' => 'Modifier', 'delete' => 'Supprimer',
        'validate' => 'Valider', 'pay' => 'Payer', 'manage' => 'Gérer', 'export' => 'Exporter', 'import' => 'Importer',
    ];

    /** Ordre des actions dans un élément. */
    private const ORDER = ['view', 'create', 'update', 'delete', 'validate', 'pay', 'manage', 'export', 'import'];

    /**
     * Éléments avec leurs permissions : clé => [label, description, options (nom => libellé)].
     *
     * @return array<string, array{label: string, description: string, options: array<string, string>}>
     */
    public static function groups(): array
    {
        $groups = [];
        foreach (Permission::orderBy('name')->pluck('name') as $name) {
            [$key, $label] = self::classify($name);
            $groups[$key][$name] = $label;
        }
        $out = [];
        foreach (self::ITEMS as $key => [$label, $description]) {
            if (! empty($groups[$key])) {
                uksort($groups[$key], fn ($a, $b) => self::rank($a) <=> self::rank($b));
                $out[$key] = ['label' => $label, 'description' => $description, 'options' => $groups[$key]];
            }
        }

        return $out;
    }

    /** @return array{0: string, 1: string} [élément, libellé de l'action] */
    public static function classify(string $name): array
    {
        if (isset(self::SPECIAL[$name])) {
            return self::SPECIAL[$name];
        }
        [$action, $module] = array_pad(explode('_', $name, 2), 2, '');
        $key = isset(self::ITEMS[$module]) ? $module : 'autres';

        return [$key, self::ACTIONS[$action] ?? ucfirst(str_replace('_', ' ', $name))];
    }

    private static function rank(string $name): int
    {
        $i = array_search(explode('_', $name, 2)[0], self::ORDER, true);

        return $i === false ? 99 : $i;
    }

    /** Libellé lisible d'un rôle (les noms techniques restent en base). */
    public static function roleLabel(string $name): string
    {
        return [
            'pres_admin' => 'Administrateur national', 'region_manager' => 'Responsable de région', 'district_manager' => 'Gestionnaire de district',
            'superviseur_bailleur' => 'Superviseur bailleur', 'convoyeur' => 'Convoyeur (application mobile)',
        ][$name] ?? $name;
    }
}
