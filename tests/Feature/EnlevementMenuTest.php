<?php

namespace Tests\Feature;

use App\Filament\Pages\Cartographie;
use App\Filament\Pages\SaisieTournee;
use App\Filament\Pages\SuiviCircuits;
use App\Filament\Pages\SuiviSaisie;
use App\Filament\Resources\Enlevements\EnlevementResource;
use App\Filament\Resources\Enlevements\Pages\CreateEnlevement;
use App\Filament\Resources\Enlevements\Pages\ListEnlevements;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Immobilisations\ImmobilisationResource;
use App\Filament\Resources\Mutualisations\MutualisationResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Resources\Vidanges\VidangeResource;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Ordre du menu principal : enlèvement, autres frais, vidanges, immobilisations, cartographie, véhicules, mutualisation. */
class EnlevementMenuTest extends TestCase
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
        // en production, l'intergiciel de Filament rattache l'enregistrement créé au district de l'URL ; ici on le reproduit
        Expense::creating(fn (Expense $e) => $e->district_id ??= $this->district->id);
    }

    public function test_the_menu_order_follows_the_requested_sequence(): void
    {
        $order = [SuiviCircuits::class, SaisieTournee::class, EnlevementResource::class, ExpenseResource::class, VidangeResource::class, ImmobilisationResource::class,
            SuiviSaisie::class, VehicleResource::class, MutualisationResource::class, Cartographie::class];
        $sorts = array_map(fn ($c) => $c::getNavigationSort(), $order);

        $this->assertSame($sorts, collect($sorts)->sort()->values()->all());
        $this->assertSame(count($sorts), count(array_unique($sorts)));

        // tous dans le menu principal, hors groupe
        foreach ([EnlevementResource::class, ExpenseResource::class, VidangeResource::class, ImmobilisationResource::class, VehicleResource::class, MutualisationResource::class] as $class) {
            $this->assertNull($class::getNavigationGroup(), $class);
        }
        $this->assertSame('Enlèvement', EnlevementResource::getNavigationLabel());
        $this->assertSame('Cartographie', SuiviSaisie::getNavigationLabel());
    }

    public function test_the_sidebar_lists_the_items_in_that_order(): void
    {
        $html = $this->get("/admin/{$this->district->id}")->assertOk()->getContent();
        preg_match_all('/class="fi-sidebar-(?:item|group)-label[^"]*"[^>]*>\s*([^<]+?)\s*</u', $html, $m);
        $labels = array_map(fn ($l) => html_entity_decode(trim($l)), $m[1]);

        $expected = ['Tableau de bord', 'Chronogramme', 'Circuits', 'Sorties Véhicules', 'Suivi des livraisons', "Saisie d'une tournée", 'Enlèvement', 'Autres frais',
            'Vidanges', 'Immobilisations', 'Cartographie', 'Véhicules', 'Mutualisation'];
        $found = array_values(array_filter($labels, fn ($l) => in_array($l, $expected, true)));

        $this->assertSame($expected, array_slice($found, 0, count($expected)), 'Menu : '.implode(' | ', $labels));
        $this->assertLessThan(array_search('Carburant', $labels, true), array_search('Mutualisation', $labels, true));   // le menu principal passe avant les groupes
        $this->assertNotContains('Vidange', $labels);
        $this->assertNotContains('Suivi en temps réel', $labels);
    }

    public function test_a_pickup_is_an_expense_of_the_right_type_for_the_chosen_espc(): void
    {
        Livewire::test(CreateEnlevement::class)
            ->fillForm(['espc_id' => $this->espc->id, 'date_depense' => '2026-10-10', 'montant' => 2500, 'beneficiaire' => 'CSR AMARAGUI'])
            ->call('create')->assertHasNoFormErrors();

        $e = Expense::sole();
        $this->assertSame(['enlevement_espc', $this->espc->id, 2500.0, $this->district->id], [$e->type, $e->espc_id, (float) $e->montant, $e->district_id]);

        Livewire::test(CreateEnlevement::class)->fillForm(['date_depense' => '2026-10-10', 'montant' => 2500])->call('create')->assertHasFormErrors(['espc_id' => 'required']);
    }

    public function test_the_pickup_list_shows_only_pickups_with_their_total(): void
    {
        $pickup = Expense::create(['district_id' => $this->district->id, 'type' => 'enlevement_espc', 'espc_id' => $this->espc->id, 'montant' => 2500, 'date_depense' => '2026-10-10']);
        $other = Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 1000, 'date_depense' => '2026-10-11']);

        Livewire::test(ListEnlevements::class)->assertCanSeeTableRecords([$pickup])->assertCanNotSeeTableRecords([$other])->assertSee('CSR AMARAGUI');
    }
}
