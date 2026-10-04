<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Charge un export produit par data:export. Remplace le contenu des tables métier ; les comptes ne sont jamais touchés. */
class DataLoad extends Command
{
    protected $signature = 'data:load {--path=database/data/snapshot.json.gz} {--if-empty : Ne charger que si aucun véhicule n\'existe encore} {--force : Remplacer les données existantes}';

    protected $description = 'Charge les données métier exportées (puis crée les accès convoyeurs)';

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        if (! is_file($path)) {
            $this->warn('Aucun fichier de données à charger : '.$this->option('path'));

            return self::SUCCESS;
        }
        if (Schema::hasTable('vehicles') && DB::table('vehicles')->exists()) {
            if ($this->option('if-empty')) {
                $this->info('La base contient déjà des données : chargement ignoré.');

                return self::SUCCESS;
            }
            if (! $this->option('force')) {
                $this->error('La base contient déjà des véhicules. Utilisez --force pour remplacer les données.');

                return self::FAILURE;
            }
        }

        $data = json_decode(gzdecode(file_get_contents($path)), true);
        $sqlite = DB::getDriverName() === 'sqlite';
        $sqlite && DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function () use ($data) {
            foreach (array_reverse(DataSnapshot::TABLES) as $table) {
                Schema::hasTable($table) && DB::table($table)->delete();
            }
            foreach (DataSnapshot::TABLES as $table) {
                $rows = $data[$table] ?? [];
                if (! $rows || ! Schema::hasTable($table)) {
                    continue;
                }
                $columns = array_flip(array_diff(Schema::getColumnListing($table), $this->generatedColumns($table)));   // les colonnes calculées se recalculent seules
                $rows = array_map(fn ($r) => array_intersect_key($r, $columns), $rows);   // colonnes absentes de cette base : ignorées
                foreach (array_chunk($rows, max(1, intdiv(800, max(1, count($rows[0]))))) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $this->line(sprintf('  %-24s %6d', $table, count($rows)));
            }
        });

        $sqlite && DB::statement('PRAGMA foreign_keys = ON');
        $this->info('Données chargées.');
        $this->call('mobile:sync-access');

        return self::SUCCESS;
    }

    /** Colonnes calculées par la base (SQLite : générées, stockées ou virtuelles) : on ne peut pas y écrire. */
    private function generatedColumns(string $table): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }

        return collect(DB::select("PRAGMA table_xinfo(\"{$table}\")"))->filter(fn ($c) => in_array($c->hidden, [2, 3]))->pluck('name')->all();
    }
}
