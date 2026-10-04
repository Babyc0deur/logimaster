<?php

namespace Tests\Feature;

use App\Domain\Fleet\AlertCenter;
use App\Domain\Reports\ReportBuilder;
use App\Filament\Pages\FinanceDashboard;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Factures\FactureResource;
use App\Models\Budget;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Le module Finance (budgets, factures) se retire d'un interrupteur sans toucher aux données ni aux dépenses d'exploitation. */
class FinanceModuleSwitchTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D', 'sync_id' => 'D', 'sync_password_hash' => 'x']);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(User::ROLE_PRES_ADMIN);
    }

    public function test_finance_removed_hides_menus_pages_and_api(): void
    {
        config(['logimaster.modules.finance' => false]);
        $this->actingAs($this->admin, 'web');
        Filament::setTenant($this->district);

        $labels = collect(Filament::getNavigation())->flatMap(fn ($g) => collect($g->getItems())->map(fn ($i) => $i->getLabel()))->all();
        foreach (['Factures', 'Budgets', 'Tableau de bord financier'] as $label) {
            $this->assertNotContains($label, $labels);
        }
        $this->assertContains('Dépenses', $labels); // dépenses d'exploitation du classeur : conservées

        $this->assertFalse(FactureResource::canAccess());
        $this->assertFalse(BudgetResource::canAccess());
        $this->assertFalse(FinanceDashboard::canAccess());
        $this->assertTrue(ExpenseResource::canAccess());
        foreach (['/factures', '/budgets', '/finance'] as $path) {
            $this->assertContains($this->get("/admin/{$this->district->id}{$path}")->getStatusCode(), [403, 404], $path);
        }
        $this->get("/admin/{$this->district->id}/expenses")->assertOk();

        Sanctum::actingAs($this->admin);
        foreach (['budgets', 'factures', 'finance/summary'] as $endpoint) {
            $this->getJson("/api/{$endpoint}")->assertNotFound();
        }
        $this->getJson('/api/vehicles')->assertOk();
    }

    public function test_reports_and_alerts_follow_the_switch(): void
    {
        config(['logimaster.modules.finance' => false]);
        $this->assertArrayNotHasKey('financier', ReportBuilder::types());
        $this->assertArrayHasKey('ddkm', ReportBuilder::types());

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/reports/types')->assertOk()->assertJsonMissing(['key' => 'financier']);
        $this->postJson('/api/reports/generate', ['type' => 'financier', 'format' => 'pdf', 'period' => '2026-09'])->assertStatus(422);

        Budget::create(['district_id' => $this->district->id, 'period' => CarbonImmutable::now()->startOfMonth()->toDateString(), 'poste' => 'carburant', 'bailleur' => '', 'montant_alloue' => 1]);
        $this->assertSame(0, AlertCenter::all([$this->district->id])->filter(fn ($a) => ($a['type'] ?? '') === 'budget' || str_contains((string) ($a['module'] ?? ''), 'budget'))->count());
    }

    public function test_finance_can_be_switched_back_on(): void
    {
        config(['logimaster.modules.finance' => true]);
        $this->actingAs($this->admin, 'web');
        Filament::setTenant($this->district);
        $this->assertTrue(FactureResource::canAccess());
        $this->assertTrue(BudgetResource::canAccess());
        $this->assertArrayHasKey('financier', ReportBuilder::types());
        $this->get("/admin/{$this->district->id}/budgets")->assertOk();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/budgets')->assertOk();
    }
}
