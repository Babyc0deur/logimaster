<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transfert des données métier d'une instance à l'autre (poste local → Render) :
 *   php artisan data:export   écrit database/data/snapshot.json.gz
 *   php artisan data:load     charge ce fichier (--if-empty : seulement si la base ne contient pas encore de véhicules)
 * Ni comptes, ni mots de passe, ni sessions, ni jetons, ni codes d'accès : les accès convoyeurs sont recréés par mobile:sync-access.
 */
class DataSnapshot extends Command
{
    protected $signature = 'data:export {--path=database/data/snapshot.json.gz} {--region= : Ne garder que les données des districts de cette région (nom) ; la liste PRES/régions/districts reste complète}';

    protected $description = 'Exporte les données métier (sans comptes ni secrets) dans un fichier compressé';

    /** Tables métier, dans l'ordre de chargement. */
    public const TABLES = [
        'pres', 'regions', 'districts', 'vehicles', 'drivers', 'personnels', 'circuits', 'espc', 'circuit_espc',
        'chronogrammes', 'chronogramme_personnel', 'sorties_vehicules', 'sortie_personnel', 'livraisons_espc',
        'ravitaillements', 'vidanges', 'immobilisations', 'expenses', 'budgets', 'factures', 'fuel_prices',
        'settings', 'district_devices', 'documents',
    ];

    /** Colonnes qui pointent vers un compte utilisateur : remises à vide (les comptes ne sont pas transférés). */
    public const USER_COLUMNS = ['valide_par', 'paye_par', 'cree_par', 'saisi_par', 'validated_by', 'uploaded_by', 'user_id'];

    /** Colonnes secrètes ou propres à un appareil. */
    public const SECRET_COLUMNS = ['code_acces', 'telephone'];

    /** Tables sans district_id : rattachées à une table parent (colonne => table parent) ; elles suivent le filtre de région. */
    private const CHILD_OF = [
        'circuit_espc' => ['circuit_id' => 'circuits', 'espc_id' => 'espc'],
        'chronogramme_personnel' => ['chronogramme_id' => 'chronogrammes', 'personnel_id' => 'personnels'],
        'sortie_personnel' => ['sortie_id' => 'sorties_vehicules', 'personnel_id' => 'personnels'],
        'livraisons_espc' => ['chronogramme_id' => 'chronogrammes', 'espc_id' => 'espc'],
    ];

    public function handle(): int
    {
        $data = [];
        $total = 0;
        $districtIds = null;
        $kept = [];   // table => ids conservés (pour filtrer les tables dépendantes)
        if ($name = $this->option('region')) {
            $region = DB::table('regions')->where('name', $name)->first();
            if (! $region) {
                $this->error("Région introuvable : {$name}");

                return self::FAILURE;
            }
            $districtIds = DB::table('districts')->where('region_id', $region->id)->pluck('id')->all();
            $this->info(sprintf('Région %s : %d districts', $region->name, count($districtIds)));
        }
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $blank = array_intersect(array_merge(self::USER_COLUMNS, self::SECRET_COLUMNS), Schema::getColumnListing($table));
            $query = DB::table($table);
            if ($districtIds !== null && ! in_array($table, ['pres', 'regions', 'districts'], true)) {
                if (isset(self::CHILD_OF[$table])) {
                    foreach (self::CHILD_OF[$table] as $col => $parent) {
                        $query->whereIn($col, $kept[$parent] ?? []);
                    }
                } elseif (in_array('district_id', Schema::getColumnListing($table), true)) {
                    $query->whereIn('district_id', $districtIds);
                } else {
                    $query->whereRaw('1 = 0');   // table sans rattachement connu : exclue d'un export partiel
                }
            }
            $rows = $query->get()->map(function ($r) use ($blank) {
                $r = (array) $r;
                foreach ($blank as $c) {
                    $r[$c] = null;
                }

                return $r;
            })->all();
            $data[$table] = $rows;
            $kept[$table] = array_column($rows, 'id');
            $total += count($rows);
            $this->line(sprintf('  %-24s %6d', $table, count($rows)));
        }

        $path = base_path($this->option('path'));
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, gzencode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 9));
        $this->info(sprintf('%d lignes exportées → %s (%.1f Mo)', $total, $this->option('path'), filesize($path) / 1048576));

        return self::SUCCESS;
    }
}
