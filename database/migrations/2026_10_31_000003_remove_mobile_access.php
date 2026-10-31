<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'application mobile n'existe plus : ses jetons de connexion et la permission « exécuter les circuits »
     * (donnée aux administrateurs et gestionnaires de district) sont supprimés.
     */
    public function up(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->where('abilities', 'like', '%"mobile"%')->delete();
        }

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'execute_circuits')->pluck('id')->all();
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
