<?php

namespace Tests\Feature;

use App\Models\FuelPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Prix du carburant d'octobre 2026 : essence 905, gasoil 725 ; les périodes précédentes gardent leur prix. */
class FuelPriceOctober2026Test extends TestCase
{
    use RefreshDatabase;

    public function test_october_2026_prices_apply_from_the_first_of_the_month_and_keep_history(): void
    {
        FuelPrice::query()->delete();   // la migration a déjà posé les prix à la création de la base de test
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 715, 'date_effet' => '2024-01-01']);
        FuelPrice::create(['type_carburant' => 'essence', 'prix' => 875, 'date_effet' => '2024-01-01']);
        (require base_path('database/migrations/2026_10_14_000004_fuel_prices_october_2026.php'))->up();

        $this->assertSame(725.0, FuelPrice::current('diesel', '2026-10-01'));
        $this->assertSame(905.0, FuelPrice::current('essence', '2026-10-15'));
        $this->assertSame(715.0, FuelPrice::current('diesel', '2026-09-30'));   // septembre garde l'ancien prix
        $this->assertSame(875.0, FuelPrice::current('essence', '2026-09-30'));
    }

    public function test_the_price_migration_does_not_overwrite_a_price_already_entered(): void
    {
        FuelPrice::query()->delete();
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 730, 'date_effet' => '2026-10-01']);
        $migration = require base_path('database/migrations/2026_10_14_000004_fuel_prices_october_2026.php');
        $migration->up();
        $migration->up();   // rejouée : aucun doublon

        $this->assertSame(1, FuelPrice::where('type_carburant', 'diesel')->whereDate('date_effet', '2026-10-01')->count());
        $this->assertSame(730.0, FuelPrice::current('diesel', '2026-10-01'));
        $this->assertSame(1, FuelPrice::where('type_carburant', 'essence')->whereDate('date_effet', '2026-10-01')->count());
    }
}
