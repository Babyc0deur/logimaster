<?php

namespace Tests\Feature;

use App\Filament\Resources\Chronogrammes\Widgets\ChronogrammeWeekGrid;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAnchorTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D', 'sync_id' => 'D', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        Filament::setTenant($this->district);
        $this->travelTo(CarbonImmutable::parse('2026-10-14'));
    }

    public function test_calendar_opens_on_the_end_of_the_default_period_in_month_view(): void
    {
        config(['logimaster.default_period' => ['from' => '2025-05-01', 'until' => '2025-10-31']]);
        $vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'AAA', 'km_actuel' => 1]);
        Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'date_prevue' => '2025-10-15', 'motif' => 'distribution']);

        Livewire::test(ChronogrammeWeekGrid::class)
            ->assertSet('mode', 'month')
            ->assertSet('monthOffset', -12)
            ->assertSee('Octobre 2025')
            ->assertSee('AAA', false) // véhicule en infobulle de la sortie du 15 octobre
            ->call('previous')->assertSee('Septembre 2025')
            ->call('today')->assertSee('Octobre 2025');
    }

    public function test_without_a_configured_period_it_opens_on_the_current_week(): void
    {
        Livewire::test(ChronogrammeWeekGrid::class)->assertSet('mode', 'week')->assertSet('weekOffset', 0)->assertSet('monthOffset', 0);
    }
}
