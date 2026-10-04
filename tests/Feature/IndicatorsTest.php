<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorService;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Immobilisation;
use App\Models\IndicatorSnapshot;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->month = CarbonImmutable::parse('2026-09-01');
        $this->travelTo($this->month->addDays(9)); // 10 septembre 2026
    }

    private function sortie(Vehicle $v, array $attrs = []): SortieVehicule
    {
        return SortieVehicule::create($attrs + [
            'owner_type' => 'district', 'owner_id' => $this->district->id, 'district_id' => $this->district->id,
            'vehicle_id' => $v->id, 'km_depart' => 1000, 'km_arrivee' => 1100, 'motif' => 'distribution', 'statut' => 'terminee',
        ]);
    }

    private function snapshots(): \Illuminate\Support\Collection
    {
        app(IndicatorService::class)->computeForDistrict($this->district->id, $this->month);

        return IndicatorSnapshot::where('district_id', $this->district->id)->get()->keyBy('indicator_key');
    }

    public function test_computes_the_nine_indicators(): void
    {
        $v1 = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'A', 'consommation_theorique' => 10]);
        Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'B']);

        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'C1', 'frequence' => 'hebdomadaire']);
        $espc = Espc::create(['district_id' => $this->district->id, 'nom' => 'E1']);
        $espc2 = Espc::create(['district_id' => $this->district->id, 'nom' => 'E2']);
        $circuit->espc()->attach([$espc->id => ['ordre' => 1], $espc2->id => ['ordre' => 2]]);
        $other = Circuit::create(['district_id' => $this->district->id, 'nom' => 'C2', 'frequence' => 'mensuel']);
        $other->espc()->attach([Espc::create(['district_id' => $this->district->id, 'nom' => 'E3'])->id => ['ordre' => 1]]);

        // 2 sorties sur C1 (1 respectée, 1 non), 1 sortie hors circuit
        $s1 = $this->sortie($v1, ['circuit_id' => $circuit->id, 'circuit_respecte' => true]);
        $this->sortie($v1, ['circuit_id' => $circuit->id, 'circuit_respecte' => false, 'km_depart' => 1100, 'km_arrivee' => 1200]);
        $this->sortie($v1, ['motif' => 'urgence', 'km_depart' => 1200, 'km_arrivee' => 1250]);

        Ravitaillement::create([
            'owner_type' => 'district', 'owner_id' => $this->district->id, 'district_id' => $this->district->id,
            'vehicle_id' => $v1->id, 'sortie_id' => $s1->id, 'litres' => 20, 'prix_unitaire' => 700,
        ]);
        Immobilisation::create([
            'owner_type' => 'district', 'owner_id' => $this->district->id, 'district_id' => $this->district->id,
            'vehicle_id' => $v1->id, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-06', 'motif' => 'panne', 'montant' => 5000,
        ]);

        $s = $this->snapshots();

        $this->assertCount(9, $s);
        $this->assertEquals(250, $s['distance_totale']->value);                       // 100 + 100 + 50
        $this->assertEquals(['distribution' => 200, 'urgence' => 50], $s['distance_totale']->breakdown['par_motif']);
        $this->assertEquals(50, $s['respect_circuits']->value);                       // 1 / 2
        $this->assertEqualsWithDelta(1 / 54 * 100, $s['utilisation_vehicules']->value, 0.001); // 1 jour d'utilisation / (24 j dispo du véhicule A + 30 j du véhicule B)
        $this->assertEquals(6 / (2 * 30) * 100, $s['taux_immobilisation']->value);    // 6 jours / (2 véhicules × 30 jours)
        $this->assertEquals(19000, $s['cout_global']->value);                         // 14000 carburant + 5000 immobilisation
        $this->assertEquals(25 / 20 * 100, $s['utilisation_rationnelle_carburant']->value); // 250 km × 10 L/100 = 25 L théoriques / 20 L réels
        $this->assertEquals(20, $s['carburant_par_motif']->value);
        $this->assertEquals(['distribution' => 20], $s['carburant_par_motif']->breakdown['par_motif']);
        $this->assertEqualsWithDelta(2 / 3 * 100, $s['respect_espc']->value, 0.001);  // E1, E2 desservis ; E3 (circuit C2) non desservi
        $this->assertEqualsWithDelta(2 / 6 * 100, $s['respect_chronogramme']->value, 0.001); // C1 hebdo : 2/5 ; C2 mensuel : 0/1
    }

    public function test_recompute_is_idempotent(): void
    {
        $this->snapshots();
        $this->snapshots();

        $this->assertSame(9, IndicatorSnapshot::count());
    }

    public function test_command_and_api_summary_aggregate_by_weighted_ratio(): void
    {
        $v = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'A']);
        $this->sortie($v);

        $this->artisan('indicators:compute', ['--period' => '2026-09'])->assertSuccessful();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_SUPERVISEUR);
        $user->districts()->attach($this->district->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/indicators/summary?period=2026-09')->assertOk()
            ->assertJsonPath('indicators.distance_totale.value', 100)
            ->assertJsonPath('indicators.utilisation_vehicules.value', round(1 / 30 * 100, 4)); // 1 jour d'utilisation sur 30
        $this->getJson('/api/indicators/distance_totale/history?months=3')->assertOk()
            ->assertJsonPath('history.0.value', 100);
        $this->getJson('/api/indicators/inconnu/history')->assertNotFound();
    }
}
