<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password'); // Default password for all

        // Ensure we have a store for the tenant users
        $store = Store::first();
        if (!$store) {
            $store = Store::create([
                'name' => 'Demo Store',
                'slug' => 'demo-store',
                'email' => 'contact@demostore.com',
                'phone' => '1234567890',
                'address' => '123 Demo St',
            ]);
        }

        // 1. Super Admin (Platform)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => $password, 'is_active' => true]
        );
        $superAdmin->assignRole('Super Admin');

        // 2. Owner (Tenant Admin)
        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Store Owner', 'password' => $password, 'is_active' => true]
        );
        $owner->assignRole('Owner');
        $owner->stores()->syncWithoutDetaching([$store->id]);

        // 3. Manager (Operational)
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Store Manager', 'password' => $password, 'is_active' => true]
        );
        $manager->assignRole('Manager');
        $manager->stores()->syncWithoutDetaching([$store->id]);

        // 4. Employee (Sales)
        $employee = User::firstOrCreate(
            ['email' => 'employee@example.com'],
            ['name' => 'Store Employee', 'password' => $password, 'is_active' => true]
        );
        $employee->assignRole('Employee');
        $employee->stores()->syncWithoutDetaching([$store->id]);

        // 5. Accountant (Financials)
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@example.com'],
            ['name' => 'Store Accountant', 'password' => $password, 'is_active' => true]
        );
        $accountant->assignRole('Accountant');
        $accountant->stores()->syncWithoutDetaching([$store->id]);
    }
}
