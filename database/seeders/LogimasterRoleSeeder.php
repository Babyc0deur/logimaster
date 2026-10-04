<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LogimasterRoleSeeder extends Seeder
{
    /** Modules métier soumis au CRUD complet. */
    public const OPERATIONAL_MODULES = [
        'vehicles', 'drivers', 'circuits', 'espc', 'sorties', 'chronogrammes', 'ravitaillements',
        'immobilisations', 'vidanges', 'documents', 'expenses', 'budgets', 'factures', 'personnels',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $operational = [];
        foreach (self::OPERATIONAL_MODULES as $module) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $operational[] = "{$action}_{$module}";
            }
        }
        $readOnly = array_values(array_filter($operational, fn ($p) => str_starts_with($p, 'view_')));

        $reporting = ['view_dashboard', 'view_indicators'];
        $admin = [
            'view_users', 'create_users', 'update_users', 'delete_users', 'validate_sorties', 'validate_ravitaillements',
            'validate_factures', 'pay_factures', 'validate_chronogrammes', 'view_reports', 'create_reports',
            'view_districts', 'manage_districts', 'view_audit_logs', 'manage_settings',
        ];

        $mobile = ['execute_circuits'];

        foreach ([...$operational, ...$reporting, ...$admin, ...$mobile] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Accès national complet
        Role::firstOrCreate(['name' => User::ROLE_PRES_ADMIN, 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        // Agrégation régionale : lecture de tous ses districts + indicateurs
        Role::firstOrCreate(['name' => User::ROLE_REGION_MANAGER, 'guard_name' => 'web'])
            ->syncPermissions([...$readOnly, ...$reporting, 'view_districts', 'view_users', 'validate_chronogrammes', 'view_reports', 'create_reports']);

        // Gestion complète de son district
        Role::firstOrCreate(['name' => User::ROLE_DISTRICT_MANAGER, 'guard_name' => 'web'])
            ->syncPermissions([...$operational, ...$reporting, 'view_districts', 'validate_sorties', 'validate_ravitaillements', 'validate_factures', 'pay_factures', 'view_reports', 'create_reports']);

        // Chef de mission / passager : exécute ses circuits depuis l'application mobile, rien d'autre
        Role::firstOrCreate(['name' => User::ROLE_CONVOYEUR, 'guard_name' => 'web'])->syncPermissions($mobile);

        // Lecture seule multi-district (bailleurs)
        Role::firstOrCreate(['name' => User::ROLE_SUPERVISEUR, 'guard_name' => 'web'])
            ->syncPermissions([...$readOnly, ...$reporting, 'view_reports']);
    }
}
