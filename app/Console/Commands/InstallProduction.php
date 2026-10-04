<?php

namespace App\Console\Commands;

use App\Models\FuelPrice;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Database\Seeders\OrganisationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Installation d'un environnement réel : rôles, organisation sanitaire, prix du carburant et un seul administrateur (jamais de comptes de démo). */
class InstallProduction extends Command
{
    protected $signature = 'logimaster:install';

    protected $description = 'Initialise une instance de production (idempotent) : rôles, PRES/régions/districts, administrateur national depuis ADMIN_EMAIL / ADMIN_PASSWORD';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => LogimasterRoleSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => OrganisationSeeder::class, '--force' => true]);
        FuelPrice::firstOrCreate(['type_carburant' => 'diesel', 'date_effet' => '2024-01-01'], ['prix' => 715]);
        FuelPrice::firstOrCreate(['type_carburant' => 'essence', 'date_effet' => '2024-01-01'], ['prix' => 875]);
        $this->info('Rôles, organisation sanitaire et prix du carburant en place.');

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (! $email || ! $password) {
            $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD absents : aucun administrateur créé.');

            return self::SUCCESS;
        }
        if (strlen($password) < 12) {
            $this->error('ADMIN_PASSWORD doit contenir au moins 12 caractères.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {   // un compte existant n'est jamais écrasé : le mot de passe a pu être changé depuis
            $user->syncRoles([User::ROLE_PRES_ADMIN]);
            $this->info("Administrateur déjà présent : {$email}");

            return self::SUCCESS;
        }
        $user = User::create(['name' => 'Administrateur national', 'email' => $email, 'password' => Hash::make($password), 'is_active' => true]);
        $user->syncRoles([User::ROLE_PRES_ADMIN]);
        $this->info("Administrateur créé : {$email}");

        return self::SUCCESS;
    }
}
