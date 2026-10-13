<?php

namespace Tests\Feature;

use App\Domain\Fleet\FleetMapSimulation;
use App\Filament\Pages\Cartographie;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Cartographie : chaque véhicule du district reçoit un circuit distinct et ses étapes géolocalisées. */
class CartographieTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_vehicle_gets_its_own_circuit_with_positions(): void
    {
        config(['logimaster.district_centres' => ['MEAGUI' => [5.4045, -6.5582]]]);
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $d = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        foreach (['D1', 'D2'] as $immat) {
            Vehicle::create(['district_id' => $d->id, 'immatriculation' => $immat]);
        }
        foreach (['CIRCUIT 1', 'CIRCUIT 2'] as $n => $nom) {
            $c = Circuit::create(['district_id' => $d->id, 'nom' => $nom]);
            $real = Espc::create(['district_id' => $d->id, 'nom' => "CSR A{$n}", 'type' => 'CSR', 'gps_lat' => 5.5, 'gps_lon' => -6.5]);
            $demo = Espc::create(['district_id' => $d->id, 'nom' => "CSR B{$n}", 'type' => 'CSR']);
            $c->espc()->attach([$real->id => ['ordre' => 1], $demo->id => ['ordre' => 2]]);
        }

        $map = app(FleetMapSimulation::class)->build($d);
        $this->assertSame(['lat' => 5.4045, 'lon' => -6.5582], $map['centre']);
        $this->assertCount(2, $map['vehicles']);
        $this->assertNotSame($map['vehicles'][0]['circuit'], $map['vehicles'][1]['circuit']);
        $this->assertSame(['lat' => 5.5, 'lon' => -6.5], array_intersect_key($map['vehicles'][0]['stops'][0], ['lat' => 0, 'lon' => 0]));   // GPS réel conservé
        $this->assertTrue($map['vehicles'][0]['stops'][1]['demo']);
        $this->assertTrue($map['simulated']);
        $this->assertEquals($map, app(FleetMapSimulation::class)->build($d));   // positions de démonstration stables

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($user);
        Filament::setTenant($d);
        Livewire::test(Cartographie::class)->assertOk()->assertSee('Heure simulée')->assertSee('D1');
    }

    public function test_region_simulation_starts_each_vehicle_from_its_own_district(): void
    {
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $ds = [];
        foreach ([['A', 5.4, -6.5], ['B', 5.9, -6.1]] as [$n, $lat, $lon]) {
            $d = District::create(['region_id' => $region->id, 'name' => "DIST {$n}", 'sync_id' => $n, 'sync_password_hash' => 'x']);
            $d->forceFill(['latitude' => $lat, 'longitude' => $lon])->save();
            Vehicle::create(['district_id' => $d->id, 'immatriculation' => "V{$n}"]);
            $c = Circuit::create(['district_id' => $d->id, 'nom' => "C{$n}"]);
            $c->espc()->attach([Espc::create(['district_id' => $d->id, 'nom' => "CSR {$n}", 'type' => 'CSR', 'gps_lat' => $lat + .05, 'gps_lon' => $lon])->id => ['ordre' => 1]]);
            $ds[] = $d;
        }
        $map = app(FleetMapSimulation::class)->buildMany(collect($ds)->each->load('region'));
        $this->assertTrue($map['multi']);
        $this->assertSame(['NAWA'], $map['regions']);
        $this->assertCount(2, $map['origins']);
        $this->assertCount(2, $map['vehicles']);
        $this->assertSame(['lat' => 5.9, 'lon' => -6.1], $map['vehicles'][1]['origin']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($user);
        Filament::setTenant($ds[0]);
        Livewire::test(Cartographie::class)->assertSee('VA')->assertSee('VB')->assertDontSee('Toute la région');
    }
}
