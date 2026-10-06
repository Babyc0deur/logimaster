<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DashboardFilters;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mois des indicateurs DDKM sur le tableau de bord : choisi dans le filtre, sinon le dernier mois avec activité pour les
 * districts et les dates filtrés — même quand le district du menu du haut (tenant) est un autre district sans activité.
 */
class IndicatorMonthFollowsFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_follows_the_filtered_districts_and_dates_not_the_menu_district(): void
    {
        config(['logimaster.default_period' => ['from' => '2025-06-11', 'until' => '2026-10-12']]);
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $empty = District::create(['region_id' => $region->id, 'name' => 'ABENGOUROU', 'sync_id' => 'A', 'sync_password_hash' => 'x']);
        $meagui = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $v = Vehicle::create(['district_id' => $meagui->id, 'immatriculation' => 'D1']);
        foreach (['2025-08-12', '2025-10-31'] as $d) {
            SortieVehicule::create(['district_id' => $meagui->id, 'vehicle_id' => $v->id, 'date_sortie' => $d, 'km_depart' => 1000, 'km_arrivee' => 1050, 'motif' => 'livraison_espc', 'statut' => 'validee']);
        }
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin);
        Filament::setTenant($empty);   // menu du haut : un district sans activité

        $this->assertSame('2025-10', DashboardFilters::indicatorMonth(['date_from' => '2025-06-11', 'date_until' => '2026-10-12'])->format('Y-m'));   // tous les districts du national
        $this->assertSame('2026-10', DashboardFilters::indicatorMonth(['district_id' => $empty->id, 'date_until' => '2026-10-12'])->format('Y-m'));   // aucune activité : mois de la date « Au »
        $this->assertSame('2025-10', DashboardFilters::indicatorMonth(['district_id' => $meagui->id, 'date_until' => '2026-10-12'])->format('Y-m'));
        $this->assertSame('2025-10', DashboardFilters::indicatorMonth(['region_id' => $region->id])->format('Y-m'));
        $this->assertSame('2025-08', DashboardFilters::indicatorMonth(['district_id' => $meagui->id, 'date_until' => '2025-09-30'])->format('Y-m'));
        $this->assertSame('2025-07', DashboardFilters::indicatorMonth(['district_id' => $meagui->id, 'periode' => '2025-07'])->format('Y-m'));
    }
}
