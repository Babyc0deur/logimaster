<?php

namespace Tests\Feature;

use App\Domain\Indicators\Calculators\CoutGlobalCalculator;
use App\Filament\Resources\Espcs\Widgets\EspcStats;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Enlèvement au district par les ESPC : l'indemnité de déplacement est un frais, compté dans le coût global. */
class EnlevementEspcTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Espc $espc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->espc = Espc::create(['district_id' => $this->district->id, 'nom' => 'CSR AMARAGUI']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_DISTRICT_MANAGER);
        $user->districts()->attach($this->district->id);
        $this->actingAs($user, 'web');
        Filament::setTenant($this->district);
        // en production, l'intergiciel de Filament rattache le frais créé au district de l'URL ; ici on le reproduit
        Expense::creating(fn (Expense $e) => $e->district_id ??= $this->district->id);
    }

    public function test_the_pickup_allowance_is_counted_in_the_global_cost_and_detailed_apart(): void
    {
        Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 2500, 'date_depense' => '2026-10-10']);
        Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 3000, 'date_depense' => '2026-10-20']);
        Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 1000, 'date_depense' => '2026-10-12']);
        Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 9999, 'date_depense' => '2026-11-05']);   // hors période

        $r = (new CoutGlobalCalculator)->compute($this->district->id, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

        $this->assertSame(6500.0, (float) $r->value);                      // 5 500 d'enlèvements + 1 000 de péage
        $this->assertSame(6500.0, (float) $r->breakdown['autres_depenses']);
        $this->assertSame(5500.0, (float) $r->breakdown['enlevements_espc']);
    }

    public function test_an_espc_is_required_for_a_pickup_and_the_expense_is_recorded_for_it(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm(['type' => 'enlevement_espc', 'montant' => 2500, 'date_depense' => '2026-10-10'])
            ->call('create')->assertHasFormErrors(['espc_id' => 'required']);

        Livewire::test(CreateExpense::class)
            ->fillForm(['type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 2500, 'date_depense' => '2026-10-10', 'beneficiaire' => 'CSR AMARAGUI'])
            ->call('create')->assertHasNoFormErrors();

        $e = Expense::sole();
        $this->assertSame(['enlevement_espc', $this->espc->id, 2500.0, $this->district->id], [$e->type, $e->espc_id, (float) $e->montant, $e->district_id]);
    }

    public function test_other_types_do_not_need_an_espc(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm(['type' => 'peage', 'montant' => 1500, 'date_depense' => '2026-10-10'])
            ->call('create')->assertHasNoFormErrors();

        $this->assertNull(Expense::sole()->espc_id);
    }

    public function test_the_list_shows_the_label_the_espc_and_filters_on_the_type(): void
    {
        $pickup = Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 2500, 'date_depense' => '2026-10-10']);
        $toll = Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 1000, 'date_depense' => '2026-10-11']);

        Livewire::test(ListExpenses::class)->assertCanSeeTableRecords([$pickup, $toll])->assertSee('Enlèvement ESPC')->assertSee('CSR AMARAGUI')
            ->filterTable('type', 'enlevement_espc')->assertCanSeeTableRecords([$pickup])->assertCanNotSeeTableRecords([$toll]);
    }

    public function test_the_espc_page_shows_what_it_received_for_its_pickups(): void
    {
        Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 2500]);
        Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 3000]);

        Livewire::test(EspcStats::class, ['record' => $this->espc])->assertSee('Enlèvements au district')->assertSee('5 500 FCFA')->assertSee('2 enlèvement(s) indemnisé(s)');
    }
}
