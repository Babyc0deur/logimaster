<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'application mobile n'est plus destinée aux convoyeurs : leurs comptes (un par chef de mission ou passager, créés
     * automatiquement), le rôle « convoyeur » et les codes d'accès provisoires encore stockés sur les fiches sont supprimés.
     * Les saisies déjà faites (carburant, frais, livraisons) sont conservées : leur auteur devient « inconnu ».
     */
    public function up(): void
    {
        $role = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'convoyeur')->first() : null;

        if ($role) {
            $ids = DB::table('model_has_roles')->where('role_id', $role->id)->where('model_type', User::class)->pluck('model_id')->all();

            foreach (array_chunk($ids, 200) as $chunk) {
                Schema::hasTable('personal_access_tokens') && DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $chunk)->delete();
                Schema::hasTable('notifications') && DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $chunk)->delete();
                Schema::hasTable('district_user') && DB::table('district_user')->whereIn('user_id', $chunk)->delete();
                Schema::hasTable('push_subscriptions') && DB::table('push_subscriptions')->whereIn('user_id', $chunk)->delete();
                DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $chunk)->delete();
                DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $chunk)->delete();
                DB::table('users')->whereIn('id', $chunk)->delete();
            }

            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        // codes d'accès provisoires (chiffrés) encore présents sur les fiches du personnel
        if (Schema::hasColumn('personnels', 'code_acces')) {
            DB::table('personnels')->whereNotNull('code_acces')->update(['code_acces' => null]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // suppression définitive : les comptes ne sont pas recréés
    }
};
