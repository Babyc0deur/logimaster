<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DashboardFilters;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Chaque utilisateur ne voit que son district (ou les districts de sa région) : sélecteur de district, filtres, API. */
class ScopeTest extends TestCase
{
    use RefreshDatabase;

    private Region $r1;

    private Region $r2;

    /** @var array<string, District> */
    private array $d = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $pres = Pres::create(['name' => 'PRES']);
        $this->r1 = Region::create(['pres_id' => $pres->id, 'name' => 'R1']);
        $this->r2 = Region::create(['pres_id' => $pres->id, 'name' => 'R2']);
        foreach ([['A', $this->r1], ['B', $this->r1], ['C', $this->r2]] as [$name, $region]) {
            $this->d[$name] = District::create(['region_id' => $region->id, 'name' => $name, 'sync_id' => "DS-{$name}", 'sync_password_hash' => 'x']);
            Vehicle::create(['district_id' => $this->d[$name]->id, 'immatriculation' => "V-{$name}"]);
        }
    }

    private function user(string $role, array $attrs = [], array $districts = []): User
    {
        $user = User::factory()->create(['is_active' => true] + $attrs);
        $user->assignRole($role);
        $user->districts()->sync(array_map(fn ($d) => $d->id, $districts));

        return $user;
    }

    public function test_region_manager_sees_every_district_of_their_region_and_only_those(): void
    {
        $region = $this->user(User::ROLE_REGION_MANAGER, ['region_id' => $this->r1->id]);

        $this->assertEqualsCanonicalizing([$this->d['A']->id, $this->d['B']->id], $region->accessibleDistrictIds());
        $this->assertSame(['A', 'B'], $region->getTenants(Filament::getPanel('admin'))->pluck('name')->all()); // liste déroulante au-dessus du menu
        $this->assertTrue($region->canAccessDistrict($this->d['B']->id));
        $this->assertFalse($region->canAccessDistrict($this->d['C']->id));

        // Un district ajouté plus tard à la région est visible sans autre manipulation.
        $new = District::create(['region_id' => $this->r1->id, 'name' => 'D', 'sync_id' => 'DS-D', 'sync_password_hash' => 'x']);
        $this->assertTrue($region->canAccessDistrict($new->id));

        Sanctum::actingAs($region);
        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/vehicles?district_id='.$this->d['C']->id)->assertForbidden();
    }

    public function test_district_manager_only_gets_their_own_district(): void
    {
        $manager = $this->user(User::ROLE_DISTRICT_MANAGER, [], [$this->d['A']]);

        $this->assertSame(['A'], $manager->getTenants(Filament::getPanel('admin'))->pluck('name')->all());
        $this->assertFalse($manager->canAccessTenant($this->d['B']));
    }

    public function test_national_admin_gets_every_district(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);

        $this->assertSame(['A', 'B', 'C'], $admin->getTenants(Filament::getPanel('admin'))->pluck('name')->all());
    }

    public function test_dashboard_filters_are_limited_to_the_users_scope(): void
    {
        $this->actingAs($this->user(User::ROLE_REGION_MANAGER, ['region_id' => $this->r1->id]), 'web');

        $this->assertEqualsCanonicalizing(['A', 'B'], DashboardFilters::accessibleDistricts()->pluck('name')->all());
        // Même en forçant une région ou un district hors périmètre, rien d'autre n'est renvoyé.
        $this->assertSame([], DashboardFilters::districtIds(['region_id' => $this->r2->id]));
        $this->assertSame([], DashboardFilters::districtIds(['district_id' => $this->d['C']->id]));
        $this->assertEqualsCanonicalizing([$this->d['A']->id, $this->d['B']->id], DashboardFilters::districtIds(['pres_id' => $this->r1->pres_id]));
    }

    public function test_region_can_be_assigned_through_the_api_only_within_scope(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/users', [
            'name' => 'Resp', 'email' => 'r@x.test', 'password' => 'password123', 'role' => User::ROLE_REGION_MANAGER, 'region_id' => $this->r1->id,
        ])->assertCreated()->json('id');

        $this->assertSame($this->r1->id, User::find($id)->region_id);
        $this->assertTrue(User::find($id)->canAccessDistrict($this->d['B']->id));
    }
}
