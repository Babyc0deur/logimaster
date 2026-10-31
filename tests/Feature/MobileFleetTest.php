<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Immobilisation;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Application mobile du bureau : planning du district et véhicules (disponibilité, vidange, échéances) en lecture. */
class MobileFleetTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private District $other;

    private Vehicle $v1;

    private Vehicle $v2;

    private array $h;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00'));   // un mercredi
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->other = District::create(['region_id' => $region->id, 'name' => 'SOUBRE', 'sync_id' => 'S', 'sync_password_hash' => 'x']);
        $this->v1 = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D1', 'km_actuel' => 42000, 'km_vidange' => 42300, 'date_assurance' => '2026-10-20']);
        $this->v2 = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D2', 'km_actuel' => 10000]);
        Vehicle::create(['district_id' => $this->other->id, 'immatriculation' => 'AUTRE']);

        $manager = User::factory()->create(['email' => 'gestionnaire@exemple.org', 'password' => 'Secret123', 'is_active' => true]);
        $manager->assignRole(User::ROLE_DISTRICT_MANAGER);
        $manager->districts()->attach($this->district->id);
        $this->app['auth']->forgetGuards();
        $this->h = ['Authorization' => 'Bearer '.$this->postJson('/api/mobile/login', ['email' => 'gestionnaire@exemple.org', 'password' => 'Secret123'])->assertOk()->json('token')];
        $this->app['auth']->forgetGuards();
    }

    private function plan(Vehicle $v, string $date, ?District $d = null): Chronogramme
    {
        return Chronogramme::create(['district_id' => ($d ?? $this->district)->id, 'vehicle_id' => $v->id, 'date_prevue' => $date, 'heure_depart' => '07:30', 'validation_statut' => 'soumis']);
    }

    private function validateMonth(?District $d = null): void
    {
        app(ChronogrammeWorkflow::class)->validate(($d ?? $this->district)->id, CarbonImmutable::parse('2026-10-01'), User::factory()->create(['is_active' => true]));
    }

    public function test_planning_lists_the_validated_runs_of_my_district_for_the_week(): void
    {
        $this->plan($this->v1, '2026-10-14');
        $this->plan($this->v2, '2026-10-16');
        $this->plan($this->v2, '2026-10-25');                                   // semaine suivante
        $draft = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $this->v1->id, 'date_prevue' => '2026-10-15']);   // brouillon
        $this->validateMonth();
        $this->plan(Vehicle::where('immatriculation', 'AUTRE')->first(), '2026-10-14', $this->other);
        $this->validateMonth($this->other);

        $res = $this->getJson('/api/mobile/planning', $this->h)->assertOk()->assertJsonPath('debut', '2026-10-12')->assertJsonPath('fin', '2026-10-18');
        $this->assertSame(['2026-10-14', '2026-10-16'], collect($res->json('data'))->pluck('date')->all());
        $this->assertNotContains($draft->id, collect($res->json('data'))->pluck('id'));

        $this->getJson('/api/mobile/planning?debut=2026-10-19&jours=7', $this->h)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/mobile/planning?district_id={$this->other->id}", $this->h)->assertForbidden();   // district hors du périmètre
    }

    public function test_vehicles_show_availability_oil_change_and_deadlines(): void
    {
        $this->plan($this->v1, '2026-10-14');
        $this->validateMonth();
        Immobilisation::create(['district_id' => $this->district->id, 'vehicle_id' => $this->v2->id, 'date_debut' => '2026-10-10', 'motif' => 'panne', 'statut' => 'en_cours']);

        $data = collect($this->getJson('/api/mobile/vehicules', $this->h)->assertOk()->json('data'))->keyBy('immatriculation');
        $this->assertSame(['D1', 'D2'], $data->keys()->all());                      // pas les véhicules d'un autre district
        $this->assertSame('reserve', $data['D1']['disponibilite']);
        $this->assertSame(300, $data['D1']['vidange_reste_km']);
        $this->assertSame(6, $data['D1']['assurance_jours']);
        $this->assertSame('immobilise', $data['D2']['disponibilite']);
        $this->assertSame('Panne mécanique', $data['D2']['immobilisation']['motif']);
    }
}
