<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorCatalog;
use App\Filament\Pages\IndicatorTargets;
use App\Filament\Resources\Districts\Pages\ManageDistricts;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    private Region $r1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $pres = Pres::create(['name' => 'PRES']);
        $this->r1 = Region::create(['pres_id' => $pres->id, 'name' => 'R1']);
        $r2 = Region::create(['pres_id' => $pres->id, 'name' => 'R2']);
        $this->d1 = District::create(['region_id' => $this->r1->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $r2->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
    }

    private function user(string $role, array $attrs = []): User
    {
        $u = User::factory()->create(['is_active' => true] + $attrs);
        $u->assignRole($role);

        return $u;
    }

    public function test_custom_targets_override_defaults_and_recolour(): void
    {
        $default = IndicatorCatalog::get('respect_chronogramme')['target'];
        $this->assertSame('warning', IndicatorCatalog::color('respect_chronogramme', 80.0));
        Setting::put('objectif_respect_chronogramme', 75);
        $this->assertSame(75.0, IndicatorCatalog::get('respect_chronogramme')['target']);
        $this->assertSame('success', IndicatorCatalog::color('respect_chronogramme', 80.0));
        $this->assertSame($default, IndicatorCatalog::defaults()['respect_chronogramme']['target']);
        // un indicateur informatif n'a pas d'objectif, même si une valeur existe
        Setting::put('objectif_distance_totale', 5);
        $this->assertNull(IndicatorCatalog::get('distance_totale')['target']);
    }

    public function test_targets_page_saves_and_is_restricted(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        Filament::setTenant($this->d1);
        Livewire::test(IndicatorTargets::class)->assertOk()
            ->fillForm(['objectif_utilisation_vehicules' => 70])->call('save')->assertHasNoFormErrors();
        $this->assertSame(70.0, IndicatorCatalog::get('utilisation_vehicules')['target']);

        $this->actingAs($this->user(User::ROLE_DISTRICT_MANAGER), 'web');
        $this->assertFalse(IndicatorTargets::canAccess());
    }

    public function test_districts_scope_and_creation_rights(): void
    {
        $region = $this->user(User::ROLE_REGION_MANAGER, ['region_id' => $this->r1->id]);
        $this->actingAs($region, 'web');
        Filament::setTenant($this->d1);
        Livewire::test(ManageDistricts::class)->assertCanSeeTableRecords([$this->d1])->assertCanNotSeeTableRecords([$this->d2]);
        $this->assertFalse(\App\Filament\Resources\Districts\DistrictResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\Districts\DistrictResource::canEdit($this->d1));

        $this->actingAs($this->user(User::ROLE_PRES_ADMIN), 'web');
        $this->assertTrue(\App\Filament\Resources\Districts\DistrictResource::canCreate());
        Livewire::test(ManageDistricts::class)->assertCanSeeTableRecords([$this->d1, $this->d2])
            ->callAction('create', ['name' => 'D3', 'region_id' => $this->r1->id])->assertHasNoActionErrors();
        $created = District::where('name', 'D3')->first();
        $this->assertNotNull($created);
        $this->assertNotEmpty($created->sync_id);
    }

    public function test_audit_log_page_requires_permission(): void
    {
        AuditLog::create(['action' => 'POST /api/vehicles', 'module' => 'api', 'metadata' => ['status' => 201]]);
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        $this->get("/admin/{$this->d1->id}/audit-logs")->assertOk()->assertSee('POST /api/vehicles');
        $this->get("/admin/{$this->d1->id}/districts")->assertOk()->assertSee('D2');
        $this->get("/admin/{$this->d1->id}/objectifs-indicateurs")->assertOk();

        $this->actingAs($this->user(User::ROLE_DISTRICT_MANAGER), 'web');
        $this->assertContains($this->get("/admin/{$this->d1->id}/audit-logs")->getStatusCode(), [403, 404]);
    }
}
