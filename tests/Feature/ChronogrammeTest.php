<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeGenerator;
use App\Domain\Indicators\IndicatorService;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\IndicatorSnapshot;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChronogrammeTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'AAA', 'km_actuel' => 1500]);
        $this->travelTo(CarbonImmutable::parse('2026-09-10'));
    }

    private function plan(string $date, string $statut = 'planifiee'): Chronogramme
    {
        return Chronogramme::create([
            'district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'date_prevue' => $date, 'statut' => $statut,
        ]);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_DISTRICT_MANAGER);
        $user->districts()->attach($this->district->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_start_creates_sortie_at_current_km_and_marks_plan_done(): void
    {
        $plan = $this->plan('2026-09-10');
        $sortie = $plan->demarrer();

        $this->assertSame(1500, $sortie->km_depart);
        $this->assertSame('en_cours', $sortie->statut);
        $this->assertSame('realisee', $plan->fresh()->statut);
        $this->assertSame($sortie->id, $plan->fresh()->sortie_id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $plan->fresh()->demarrer(); // déjà réalisée
    }

    public function test_api_crud_and_start(): void
    {
        $this->manager();
        $id = $this->postJson('/api/chronogrammes', [
            'district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'date_prevue' => '2026-09-12', 'heure_depart' => '07:30',
        ])->assertCreated()->json('id');

        $this->getJson('/api/chronogrammes?from=2026-09-01&to=2026-09-30')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/chronogrammes?from=2026-10-01')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/chronogrammes/{$id}/start")->assertCreated()->assertJsonPath('sortie.km_depart', 1500);
        $this->postJson("/api/chronogrammes/{$id}/start")->assertStatus(422);
    }

    public function test_generator_creates_plans_from_circuit_frequency_without_duplicates(): void
    {
        Circuit::create(['district_id' => $this->district->id, 'nom' => 'Hebdo', 'frequence' => 'hebdomadaire']);
        Circuit::create(['district_id' => $this->district->id, 'nom' => 'Mensuel', 'frequence' => 'mensuel']);
        $generator = app(ChronogrammeGenerator::class);
        $month = CarbonImmutable::parse('2026-09-01');

        // Septembre 2026 compte 4 lundis (7, 14, 21, 28) + 1 livraison mensuelle
        $this->assertSame(5, $generator->generate($this->district->id, $month, $this->vehicle->id, null, 1));
        $this->assertSame(0, $generator->generate($this->district->id, $month, $this->vehicle->id, null, 1));
        $this->assertEqualsCanonicalizing(['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'], $generator->datesFor('hebdomadaire', $month, 1));
        $this->assertCount(22, $generator->datesFor('quotidien', $month, 1)); // jours ouvrés de septembre 2026
    }

    public function test_respect_chronogramme_indicator_uses_the_planning_when_present(): void
    {
        // Aujourd'hui = 10/09 : 2 réalisées, 1 en retard, 1 reportée, 1 annulée (exclue), 1 future (exclue)
        $this->plan('2026-09-02', 'realisee');
        $this->plan('2026-09-05', 'realisee');
        $this->plan('2026-09-08', 'planifiee');
        $this->plan('2026-09-09', 'reportee');
        $this->plan('2026-09-03', 'annulee');
        $this->plan('2026-09-20', 'planifiee');

        app(IndicatorService::class)->computeForDistrict($this->district->id, CarbonImmutable::parse('2026-09-01'));
        $snapshot = IndicatorSnapshot::where('indicator_key', 'respect_chronogramme')->first();

        $this->assertEquals(50, $snapshot->value); // 2 / 4
        $this->assertSame('sites', $snapshot->breakdown['source']);   // sorties sans liste de sites : une unité chacune
    }

    public function test_read_only_role_cannot_plan(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_SUPERVISEUR);
        $user->districts()->attach($this->district->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/chronogrammes')->assertOk();
        $this->postJson('/api/chronogrammes', [
            'district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'date_prevue' => '2026-09-12',
        ])->assertForbidden();
    }
}
