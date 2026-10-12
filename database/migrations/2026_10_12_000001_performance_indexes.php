<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Index composites pour les requêtes des indicateurs, des rapports et du suivi de la saisie (district + date, période des instantanés). */
return new class extends Migration
{
    private const INDEXES = [
        'sorties_vehicules' => [['district_id', 'date_sortie'], ['vehicle_id', 'date_sortie']],
        'ravitaillements' => [['district_id', 'date_ravitaillement'], ['vehicle_id', 'date_ravitaillement']],
        'expenses' => [['district_id', 'date_depense']],
        'vidanges' => [['district_id', 'date']],
        'immobilisations' => [['district_id', 'date_debut'], ['vehicle_id', 'date_debut']],
        'vehicles' => [['district_id']],
        'circuits' => [['district_id']],
        'espc' => [['district_id']],
        'indicator_snapshots' => [['period', 'indicator_key'], ['period', 'district_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            foreach ($sets as $columns) {
                if (! Schema::hasTable($table) || ! $this->hasColumns($table, $columns)) {
                    continue;
                }
                $name = $table.'_'.implode('_', $columns).'_perf';
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            foreach ($sets as $columns) {
                if (Schema::hasTable($table)) {
                    $name = $table.'_'.implode('_', $columns).'_perf';
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return false;
            }
        }

        return true;
    }
};
