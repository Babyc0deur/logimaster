<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Filament\Resources\Chronogrammes\Widgets\ChronogrammeWeekGrid;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class PlanningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Vehicle $vehicle;

    private User $manager;

    private User $superviseur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'AAA', 'km_actuel' => 1000]);
        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->assignRole(User::ROLE_DISTRICT_MANAGER);
        $this->manager->districts()->attach($this->district->id);
        $this->superviseur = User::factory()->create(['is_active' => true]);
        $this->superviseur->assignRole(User::ROLE_PRES_ADMIN);
        $this->travelTo(CarbonImmutable::parse('2026-09-10'));
    }

    private function plan(string $date, array $extra = []): Chronogramme
    {
        return Chronogramme::create(array_merge(['district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'date_prevue' => $date], $extra));
    }

    public function test_workflow_submit_validate_lock_and_reopen(): void
    {
        $plan = $this->plan('2026-09-20');
        $w = app(ChronogrammeWorkflow::class);
        $month = CarbonImmutable::parse('2026-09-01');

        $this->assertSame(1, $w->submit($this->district->id, $month, $this->manager));
        $this->assertSame('soumis', $plan->fresh()->validation_statut);
        $this->assertSame(1, $w->validate($this->district->id, $month, $this->superviseur));
        $this->assertTrue($plan->fresh()->isLocked());

        try {
            $plan->fresh()->update(['date_prevue' => '2026-09-22']);
            $this->fail('Un planning validé ne doit pas être modifiable.');
        } catch (ValidationException) {
            $this->assertSame('2026-09-20', $plan->fresh()->date_prevue->toDateString());
        }

        $this->assertSame(1, $w->reopen($this->district->id, $month, $this->superviseur));
        $this->assertFalse($plan->fresh()->isLocked());
    }

    public function test_refusal_stores_reason_and_unlocks(): void
    {
        $plan = $this->plan('2026-09-20');
        $w = app(ChronogrammeWorkflow::class);
        $month = CarbonImmutable::parse('2026-09-01');
        $w->submit($this->district->id, $month, $this->manager);
        $w->refuse($this->district->id, $month, $this->superviseur, 'Dates incohérentes');
        $fresh = $plan->fresh();
        $this->assertSame('refuse', $fresh->validation_statut);
        $this->assertSame('Dates incohérentes', $fresh->motif_refus);
        $this->assertFalse($fresh->isLocked());
    }

    public function test_api_workflow_requires_permissions_and_locks_edits(): void
    {
        $plan = $this->plan('2026-09-20');
        $body = ['district_id' => $this->district->id, 'month' => '2026-09'];

        Sanctum::actingAs($this->manager);
        $this->postJson('/api/chronogrammes/submit', $body)->assertOk()->assertJson(['count' => 1]);
        $this->postJson('/api/chronogrammes/validate', $body)->assertForbidden();

        Sanctum::actingAs($this->superviseur);
        $this->postJson('/api/chronogrammes/refuse', $body)->assertUnprocessable();
        $this->postJson('/api/chronogrammes/validate', $body)->assertOk()->assertJson(['count' => 1]);
        $this->patchJson("/api/chronogrammes/{$plan->id}", ['date_prevue' => '2026-09-25'])->assertUnprocessable();
    }

    public function test_drag_and_drop_moves_plan_and_respects_rules(): void
    {
        $this->actingAs($this->superviseur, 'web');
        Filament::setTenant($this->district);
        $plan = $this->plan('2026-09-20');
        $other = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'BBB', 'km_actuel' => 1]);

        Livewire::test(ChronogrammeWeekGrid::class)->call('movePlan', $plan->id, '2026-09-22', $other->id);
        $this->assertSame('2026-09-22', $plan->fresh()->date_prevue->toDateString());
        $this->assertSame($other->id, $plan->fresh()->vehicle_id);

        // passé refusé
        Livewire::test(ChronogrammeWeekGrid::class)->call('movePlan', $plan->id, '2026-09-01');
        $this->assertSame('2026-09-22', $plan->fresh()->date_prevue->toDateString());

        // double réservation refusée
        $this->plan('2026-09-24', ['vehicle_id' => $other->id]);
        Livewire::test(ChronogrammeWeekGrid::class)->call('movePlan', $plan->id, '2026-09-24', $other->id);
        $this->assertSame('2026-09-22', $plan->fresh()->date_prevue->toDateString());

        // verrouillé
        $plan->update(['validation_statut' => 'soumis']);
        app(ChronogrammeWorkflow::class)->validate($this->district->id, CarbonImmutable::parse('2026-09-01'), $this->superviseur, [$plan->id]);
        Livewire::test(ChronogrammeWeekGrid::class)->call('movePlan', $plan->id, '2026-09-26');
        $this->assertSame('2026-09-22', $plan->fresh()->date_prevue->toDateString());
    }

    public function test_grid_renders_in_both_modes(): void
    {
        $this->actingAs($this->superviseur, 'web');
        Filament::setTenant($this->district);
        $this->plan('2026-09-12');
        Livewire::test(ChronogrammeWeekGrid::class)->assertOk()->call('setMode', 'month')->assertOk()->call('next')->assertOk();
    }

    public function test_livraisons_api_update_and_summary(): void
    {
        $espc = Espc::create(['district_id' => $this->district->id, 'nom' => 'E1']);
        $plan = $this->plan('2026-09-08');
        $plan->espc()->attach($espc->id, ['ordre' => 1]);
        $l = LivraisonEspc::first();

        Sanctum::actingAs($this->manager);
        $this->patchJson("/api/livraisons/{$l->id}", ['statut' => 'non_livre'])->assertUnprocessable();
        $this->patchJson("/api/livraisons/{$l->id}", ['statut' => 'non_livre', 'raison_non_livraison' => 'Route coupée'])->assertOk();
        $this->getJson('/api/livraisons/summary')->assertOk()->assertJson(['prevues' => 1, 'non_livrees' => 1]);
        $this->patchJson("/api/livraisons/{$l->id}", ['statut' => 'livre', 'date_livraison' => '2026-09-08'])->assertOk();
        $this->getJson('/api/livraisons/summary')->assertJson(['livrees' => 1, 'dans_les_delais' => 1]);
        $this->getJson("/api/espc/{$espc->id}/deliveries")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_circuit_api_accepts_stage_distances_and_departure(): void
    {
        $e1 = Espc::create(['district_id' => $this->district->id, 'nom' => 'E1']);
        $e2 = Espc::create(['district_id' => $this->district->id, 'nom' => 'E2']);
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/circuits', [
            'district_id' => $this->district->id, 'nom' => 'C1', 'point_depart' => 'DDKM', 'depart_lat' => 5.3, 'depart_lon' => -4.0,
            'espc' => [['id' => $e1->id, 'ordre' => 1, 'distance_km' => 12.5], ['id' => $e2->id, 'ordre' => 2, 'distance_km' => 7]],
        ])->assertCreated();
        $circuit = Circuit::first();
        $this->assertSame('DDKM', $circuit->point_depart);
        $this->assertEquals(12.5, $circuit->espc->first()->pivot->distance_km);
        $this->assertCount(2, $circuit->etapesDetail());
    }
}
