<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Rôles : permissions rangées par élément (Véhicules, Chronogramme…), en français ; enregistrement sans perte. */
class RoleFormTest extends TestCase
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
        $admin->districts()->attach($this->district->id);
        $this->actingAs($admin, 'web');
        Filament::setTenant($this->district);
    }

    public function test_every_permission_is_in_exactly_one_item_with_a_french_label(): void
    {
        $groups = PermissionCatalog::groups();
        $all = collect($groups)->flatMap(fn ($g) => array_keys($g['options']));
        $this->assertSame(\Spatie\Permission\Models\Permission::count(), $all->count());
        $this->assertSame($all->count(), $all->unique()->count());
        $this->assertSame(['Voir', 'Créer', 'Modifier', 'Supprimer'], array_values($groups['vehicles']['options']));
        $this->assertSame('Valider', $groups['chronogrammes']['options']['validate_chronogrammes']);
        $this->assertArrayHasKey('execute_circuits', $groups['mobile']['options']);
    }

    public function test_editing_a_role_keeps_and_saves_its_permissions_per_item(): void
    {
        $role = Role::findByName(User::ROLE_DISTRICT_MANAGER);
        $before = $role->permissions->pluck('name')->sort()->values()->all();

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->assertSet('data.permissions_vehicles', fn ($v) => in_array('view_vehicles', $v, true))
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($before, $role->fresh()->permissions->pluck('name')->sort()->values()->all());   // rien de perdu

        Livewire::test(EditRole::class, ['record' => $role->getKey()])
            ->set('data.permissions_vehicles', ['view_vehicles'])
            ->call('save')->assertHasNoFormErrors();
        $after = $role->fresh()->permissions->pluck('name');
        $this->assertTrue($after->contains('view_vehicles'));
        $this->assertFalse($after->contains('delete_vehicles'));
        $this->assertTrue($after->contains('view_chronogrammes'));   // les autres éléments ne bougent pas
    }

    public function test_creating_a_role_with_permissions_from_several_items(): void
    {
        Livewire::test(CreateRole::class)
            ->set('data.name', 'logisticien')
            ->set('data.permissions_vehicles', ['view_vehicles', 'update_vehicles'])
            ->set('data.permissions_chronogrammes', ['view_chronogrammes'])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame(['update_vehicles', 'view_chronogrammes', 'view_vehicles'], Role::findByName('logisticien')->permissions->pluck('name')->sort()->values()->all());
        $this->get('/admin/'.$this->district->id.'/roles')->assertOk()->assertSee('Gestionnaire de district');
    }
}
