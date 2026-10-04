<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
    }

    private function actingAsRole(string $role, array $districts = []): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->sync(array_map(fn ($d) => $d->id, $districts));
        Sanctum::actingAs($user);

        return $user;
    }

    private function vehicle(District $d, string $immat): Vehicle
    {
        return Vehicle::create(['district_id' => $d->id, 'immatriculation' => $immat]);
    }

    public function test_login_returns_token_and_rejects_bad_password(): void
    {
        $user = User::factory()->create(['email' => 'a@b.test', 'password' => 'secret123', 'is_active' => true]);
        $user->assignRole(User::ROLE_PRES_ADMIN);

        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'a@b.test', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['roles', 'permissions']]);
    }

    public function test_requests_without_token_are_rejected(): void
    {
        $this->getJson('/api/vehicles')->assertUnauthorized();
    }

    public function test_district_manager_only_sees_own_district(): void
    {
        $mine = $this->vehicle($this->d1, 'AAA');
        $other = $this->vehicle($this->d2, 'BBB');
        $this->actingAsRole(User::ROLE_DISTRICT_MANAGER, [$this->d1]);

        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/vehicles/{$other->id}")->assertNotFound();
        $this->getJson('/api/vehicles?district_id='.$this->d2->id)->assertForbidden();
        $this->postJson('/api/vehicles', ['district_id' => $this->d2->id, 'immatriculation' => 'CCC'])->assertForbidden();
    }

    public function test_district_manager_can_crud_a_vehicle(): void
    {
        $this->actingAsRole(User::ROLE_DISTRICT_MANAGER, [$this->d1]);

        $id = $this->postJson('/api/vehicles', [
            'district_id' => $this->d1->id, 'immatriculation' => '1234 AB 01', 'type_carburant' => 'diesel',
        ])->assertCreated()->json('id');

        $this->patchJson("/api/vehicles/{$id}", ['km_actuel' => 500])->assertOk()
            ->assertJsonPath('km_actuel', 500)->assertJsonPath('version', 2);
        $this->postJson('/api/vehicles', ['district_id' => $this->d1->id, 'immatriculation' => '1234 AB 01'])
            ->assertStatus(422);
        $this->getJson("/api/vehicles/{$id}/stats")->assertOk()->assertJsonPath('nb_sorties', 0);
        $this->deleteJson("/api/vehicles/{$id}")->assertNoContent();
        $this->getJson("/api/vehicles/{$id}")->assertNotFound();
    }

    public function test_read_only_roles_cannot_write(): void
    {
        $this->actingAsRole(User::ROLE_SUPERVISEUR, [$this->d1, $this->d2]);
        $this->vehicle($this->d1, 'AAA');
        $this->vehicle($this->d2, 'BBB');

        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/vehicles', ['district_id' => $this->d1->id, 'immatriculation' => 'ZZZ'])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_sortie_closing_updates_vehicle_km_and_status(): void
    {
        $this->actingAsRole(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $vehicle = $this->vehicle($this->d1, 'AAA');

        $id = $this->postJson('/api/sorties', [
            'district_id' => $this->d1->id, 'vehicle_id' => $vehicle->id, 'km_depart' => 100, 'motif' => 'distribution',
        ])->assertCreated()->json('id');

        $this->patchJson("/api/sorties/{$id}", ['km_arrivee' => 50])->assertStatus(422);
        $this->patchJson("/api/sorties/{$id}", ['km_arrivee' => 180])->assertOk()->assertJsonPath('statut', 'terminee');
        $this->assertSame(180, $vehicle->fresh()->km_actuel);
    }

    public function test_cannot_reference_vehicle_of_another_district(): void
    {
        $this->actingAsRole(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $foreign = $this->vehicle($this->d2, 'BBB');

        $this->postJson('/api/sorties', [
            'district_id' => $this->d1->id, 'vehicle_id' => $foreign->id, 'km_depart' => 1, 'motif' => 'distribution',
        ])->assertStatus(422);
    }

    public function test_dashboard_alerts_flag_upcoming_oil_change(): void
    {
        $this->actingAsRole(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'AAA', 'km_actuel' => 9800, 'km_vidange' => 10000]);

        $this->getJson('/api/dashboard/alerts')->assertOk()->assertJsonPath('0.type', 'vidange');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('vehicules.total', 1)->assertJsonPath('alertes', 1);
    }

    public function test_user_admin_scoping_and_role_escalation_guard(): void
    {
        $this->actingAsRole(User::ROLE_PRES_ADMIN);
        $id = $this->postJson('/api/users', [
            'name' => 'Gest', 'email' => 'g@x.test', 'password' => 'password123',
            'role' => User::ROLE_DISTRICT_MANAGER, 'district_ids' => [$this->d1->id],
        ])->assertCreated()->json('id');
        $this->assertTrue(User::find($id)->canAccessDistrict($this->d1->id));
        $this->assertFalse(User::find($id)->canAccessDistrict($this->d2->id));

        $this->actingAsRole(User::ROLE_REGION_MANAGER, [$this->d1]);
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'password123', 'role' => User::ROLE_PRES_ADMIN])
            ->assertForbidden(); // region_manager n'a pas create_users
    }

    public function test_rotate_sync_credentials_returns_password_once_and_audits(): void
    {
        $admin = $this->actingAsRole(User::ROLE_PRES_ADMIN);
        $res = $this->postJson("/api/districts/{$this->d1->id}/sync-credentials/rotate")->assertOk();

        $this->assertTrue(\Hash::check($res->json('sync_password'), $this->d1->fresh()->sync_password_hash));
        $this->getJson('/api/audit-logs')->assertOk()->assertJsonPath('data.0.user_id', $admin->id);
    }
}
