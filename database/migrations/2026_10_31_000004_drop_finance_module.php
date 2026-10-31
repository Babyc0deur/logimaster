<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Le module Finance est supprimé : budgets, factures, leurs permissions et les rapports financiers déjà générés.
     * Les dépenses (« autres frais »), le carburant et les vidanges ne sont pas touchés.
     */
    public function up(): void
    {
        // rapports financiers : planifications et fichiers déjà générés
        foreach (['reports', 'report_schedules'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'type')) {
                continue;
            }
            if ($table === 'reports' && Schema::hasColumn($table, 'fichier_path')) {
                foreach (DB::table($table)->where('type', 'financier')->pluck('fichier_path') as $path) {
                    $path && Storage::disk('local')->delete($path);
                }
            }
            DB::table($table)->where('type', 'financier')->delete();
        }

        Schema::dropIfExists('factures');
        Schema::dropIfExists('budgets');

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where(function ($q) {
                $q->where('name', 'like', '%\_budgets')->orWhere('name', 'like', '%\_factures');
            })->pluck('id')->all();
            if ($ids) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // suppression définitive
    }
};
