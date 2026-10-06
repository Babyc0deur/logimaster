<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** À l'arrivée : district MEAGUI ouvert par défaut, tableau de bord sur tout le mois d'octobre 2025. */
class LandingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_district_and_dashboard_period(): void
    {
        config(['logimaster.default_district' => 'MEAGUI', 'logimaster.dashboard_period' => ['from' => '2025-10-01', 'until' => '2025-10-31']]);
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $abengourou = District::create(['region_id' => $region->id, 'name' => 'ABENGOUROU', 'sync_id' => 'A', 'sync_password_hash' => 'x']);
        $meagui = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);

        $national = User::factory()->create(['is_active' => true]);
        $national->assignRole(User::ROLE_PRES_ADMIN);
        $this->assertSame($meagui->id, $national->getDefaultTenant(Filament::getPanel('admin'))->getKey());

        // district non accessible : premier district de l'utilisateur
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole(User::ROLE_DISTRICT_MANAGER);
        $other->districts()->attach($abengourou->id);
        $this->assertSame($abengourou->id, $other->getDefaultTenant(Filament::getPanel('admin'))->getKey());

        $this->actingAs($national);
        $this->get('/admin')->assertRedirect("/admin/{$meagui->id}");
        Filament::setTenant($meagui);
        Livewire::test(Dashboard::class)
            ->assertSet('filters.date_from', '2025-10-01 00:00:00')
            ->assertSet('filters.date_until', '2025-10-31 23:59:59')
            ->assertSet('filters.district_id', $meagui->id);
    }
}
