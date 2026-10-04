<?php

namespace Tests\Feature;

use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Driver;
use App\Models\Facture;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Les ressources et pages Filament appliquent les mêmes permissions que l'API. */
class FilamentPermissionsTest extends TestCase
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
        $this->vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'AAA', 'km_actuel' => 1]);
    }

    private function as(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'region_id' => $role === User::ROLE_REGION_MANAGER ? $this->district->region_id : null]);
        $user->assignRole($role);
        $role === User::ROLE_DISTRICT_MANAGER || $role === User::ROLE_SUPERVISEUR ? $user->districts()->attach($this->district->id) : null;
        $this->actingAs($user, 'web');

        return $user;
    }

    private function code(string $path): int
    {
        return $this->get("/admin/{$this->district->id}{$path}")->getStatusCode();
    }

    public function test_read_only_roles_cannot_create_or_edit(): void
    {
        foreach ([User::ROLE_SUPERVISEUR, User::ROLE_REGION_MANAGER] as $role) {
            $this->as($role);
            $this->assertSame(200, $this->code('/vehicles'), "{$role} doit pouvoir consulter les véhicules");
            $this->assertSame(200, $this->code("/vehicles/{$this->vehicle->id}"), "{$role} voit la fiche");
            $this->assertContains($this->code('/vehicles/create'), [403, 404], "{$role} ne crée pas de véhicule");
            $this->assertContains($this->code("/vehicles/{$this->vehicle->id}/edit"), [403, 404], "{$role} ne modifie pas un véhicule");
            $this->assertContains($this->code('/chronogrammes/create'), [403, 404], "{$role} ne planifie pas");
            $this->assertContains($this->code('/personnels/create'), [403, 404]);
            $this->assertContains($this->code('/factures/create'), [403, 404]);
        }
    }

    public function test_district_manager_works_on_operations_but_not_on_administration(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        foreach (['/vehicles', '/vehicles/create', '/drivers/create', '/chronogrammes/create', '/personnels/create', '/factures/create', '/carburant', '/calendrier-maintenance', ''] as $path) {
            $this->assertSame(200, $this->code($path), "district_manager → {$path}");
        }
        foreach (['/roles', '/users', '/audit-logs', '/seuils-alerte', '/fuel-prices', '/objectifs-indicateurs'] as $path) {
            $this->assertContains($this->code($path), [403, 404], "district_manager ne doit pas accéder à {$path}");
        }
    }

    public function test_roles_are_reserved_to_the_national_admin(): void
    {
        $this->as(User::ROLE_REGION_MANAGER);
        $this->assertContains($this->code('/roles'), [403, 404]);
        $this->as(User::ROLE_PRES_ADMIN);
        $this->assertSame(200, $this->code('/roles'));
        $this->assertSame(200, $this->code('/users'));
    }

    public function test_policies_follow_permissions(): void
    {
        $superviseur = $this->as(User::ROLE_SUPERVISEUR);
        $this->assertTrue($superviseur->can('viewAny', Vehicle::class));
        $this->assertFalse($superviseur->can('create', Vehicle::class));
        $this->assertFalse($superviseur->can('update', $this->vehicle));
        $this->assertFalse($superviseur->can('delete', $this->vehicle));
        $this->assertFalse($superviseur->can('viewAny', Role::class));
        $this->assertFalse($superviseur->can('create', Driver::class));

        $manager = $this->as(User::ROLE_DISTRICT_MANAGER);
        $this->assertTrue($manager->can('update', $this->vehicle));
        $this->assertTrue($manager->can('create', Facture::class));
        // les livraisons suivent le chronogramme mais ne se créent ni ne se suppriment à la main
        $this->assertTrue($manager->can('viewAny', LivraisonEspc::class));
        $this->assertFalse($manager->can('create', LivraisonEspc::class));
        $this->assertFalse($manager->can('create', Role::class));
        $this->assertTrue($manager->can('update', Chronogramme::class));
    }

    public function test_dashboard_and_reports_need_their_permission(): void
    {
        $user = $this->as(User::ROLE_DISTRICT_MANAGER);
        Role::findByName(User::ROLE_DISTRICT_MANAGER)->revokePermissionTo('view_dashboard');
        $user->forgetCachedPermissions();
        $this->assertContains($this->code(''), [403, 404]);
    }
}
