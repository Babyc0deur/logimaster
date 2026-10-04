<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions by module
        $permissions = [
            // Users
            'view_users', 'create_users', 'update_users', 'delete_users',
            
            // Products
            'view_products', 'create_products', 'update_products', 'delete_products',
            
            // Categories
            'view_categories', 'create_categories', 'update_categories', 'delete_categories',
            
            // Suppliers
            'view_suppliers', 'create_suppliers', 'update_suppliers', 'delete_suppliers',
            
            // Transactions
            'view_transactions', 'create_transactions', 'update_transactions', 'delete_transactions',
            
            // Stores
            'view_stores', 'create_stores', 'update_stores', 'delete_stores',

            // Financials
            'view_financial_reports',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin - Full Platform Access
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Owner - Full Tenant Access
        $owner = Role::firstOrCreate(['name' => 'Owner']);
        $owner->givePermissionTo([
            'view_users', 'create_users', 'update_users', 'delete_users',
            'view_products', 'create_products', 'update_products', 'delete_products',
            'view_categories', 'create_categories', 'update_categories', 'delete_categories',
            'view_suppliers', 'create_suppliers', 'update_suppliers', 'delete_suppliers',
            'view_transactions', 'create_transactions', 'update_transactions', 'delete_transactions',
            'view_stores', 'update_stores', // Can manage their own store settings
            'view_financial_reports',
        ]);

        // Manager - Operational Management
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $manager->givePermissionTo([
            'view_products', 'create_products', 'update_products',
            'view_categories', 'create_categories', 'update_categories',
            'view_suppliers', 'create_suppliers', 'update_suppliers',
            'view_transactions', 'create_transactions', 'update_transactions',
            'view_users', // Can view staff
        ]);

        // Employee - Limited Access (POS/Sales)
        $employee = Role::firstOrCreate(['name' => 'Employee']);
        $employee->givePermissionTo([
            'view_products',
            'view_categories',
            'view_transactions', 'create_transactions', // Can sell
        ]);

        // Accountant - Financial View Only
        $accountant = Role::firstOrCreate(['name' => 'Accountant']);
        $accountant->givePermissionTo([
            'view_transactions',
            'view_financial_reports',
            'view_products', // Context
            'view_suppliers', // Context
        ]);

        // Assign Super Admin role to the default admin user
        $adminUser = \App\Models\User::where('email', 'admin@example.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('Super Admin');
        }
    }
}
