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

class ScopeFiltersLinkTest extends TestCase
{
    use RefreshDatabase;

    private Pres $pres1;

    private Region $r1;

    private Region $r2;

    private District $a;

    private District $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->pres1 = Pres::create(['name' => 'PRES 1']);
        $pres2 = Pres::create(['name' => 'PRES 2']);
        $this->r1 = Region::create(['pres_id' => $this->pres1->id, 'name' => 'R1']);
        $this->r2 = Region::create(['pres_id' => $pres2->id, 'name' => 'R2']);
        $this->a = District::create(['region_id' => $this->r1->id, 'name' => 'A', 'sync_id' => 'A', 'sync_password_hash' => 'x']);
        $this->c = District::create(['region_id' => $this->r2->id, 'name' => 'C', 'sync_id' => 'C', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        Filament::setTenant($this->a);
    }

    public function test_filters_open_on_the_current_district_with_its_region_and_pres(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSet('filters.district_id', $this->a->id)
            ->assertSet('filters.region_id', $this->r1->id)
            ->assertSet('filters.pres_id', $this->pres1->id);
    }

    public function test_choosing_a_district_selects_its_region_and_pres(): void
    {
        Livewire::test(Dashboard::class)
            ->set('filters.district_id', $this->c->id)
            ->assertSet('filters.region_id', $this->r2->id)
            ->assertSet('filters.pres_id', $this->r2->pres_id);
    }

    public function test_choosing_a_region_selects_its_pres_and_clears_the_district(): void
    {
        Livewire::test(Dashboard::class)
            ->set('filters.region_id', $this->r2->id)
            ->assertSet('filters.pres_id', $this->r2->pres_id)
            ->assertSet('filters.district_id', null);
    }
}
