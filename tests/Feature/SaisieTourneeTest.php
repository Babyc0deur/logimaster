<?php

namespace Tests\Feature;

use App\Filament\Pages\SaisieTournee;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Page « Saisie d'une tournée » : une seule page pour recopier le carnet de bord d'une sortie. */
class SaisieTourneeTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Chronogramme $plan;

    /** @var array<int, string> */
    private array $stops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 18:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D55031', 'km_actuel' => 50000, 'type_carburant' => 'diesel']);
        FuelPrice::query()->delete();
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 725, 'date_effet' => '2026-10-01']);
        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'CIRCUIT 5']);
        foreach (['CSR A', 'CSU B', 'DR C'] as $i => $nom) {
            $circuit->espc()->attach(Espc::create(['district_id' => $this->district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => 10]);
        }
        $this->plan = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'circuit_id' => $circuit->id, 'date_prevue' => '2026-10-14', 'validation_statut' => 'valide']);
        $this->stops = LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('id')->all();
    }

    private function as(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->attach($this->district->id);
        $this->actingAs($user, 'web');
        Filament::setTenant($this->district);

        return $user;
    }

    private function page()
    {
        return Livewire::withQueryParams(['plan' => $this->plan->id])->test(SaisieTournee::class);
    }

    public function test_old_runs_are_found_by_date_circuit_or_vehicle_and_can_be_opened(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $old = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $this->plan->vehicle_id, 'circuit_id' => $this->plan->circuit_id, 'date_prevue' => '2025-07-03', 'validation_statut' => 'valide']);

        $component = $this->page()->instance();
        $options = fn (?string $q = null) => (fn () => $this->planOptions($q))->call($component);

        $this->assertArrayHasKey($old->id, $options());                 // plus de 60 jours : toujours proposée
        $this->assertSame([$old->id], array_keys($options('03/07/2025')));
        $this->assertArrayHasKey($old->id, $options('CIRCUIT 5'));
        $this->assertArrayHasKey($old->id, $options('D55031'));
        $this->assertSame([], $options('03/08/2025'));

        Livewire::withQueryParams(['plan' => $old->id])->test(SaisieTournee::class)->assertSet('data.plan_id', $old->id);
    }

    public function test_the_page_opens_on_the_run_with_the_vehicle_odometer_and_all_the_sites(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);

        $page = $this->page()->assertOk()->assertSet('data.plan_id', $this->plan->id)->assertSet('data.km_depart', 50000)
            ->assertSet('data.ravitaillement.prix_unitaire', 725.0);
        $this->assertCount(3, $page->get('data.sites'));
        $this->assertSame(['CSR A', 'CSU B', 'DR C'], collect($page->get('data.sites'))->pluck('nom')->all());
    }

    public function test_the_default_run_is_todays_run_when_no_plan_is_given(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);

        Livewire::test(SaisieTournee::class)->assertSet('data.plan_id', $this->plan->id);
    }

    public function test_saving_records_the_whole_tour_then_reloads_the_saved_state(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $page = $this->page();
        $keys = array_keys($page->get('data.sites'));   // clés des lignes du répéteur, dans l'ordre du circuit
        $key = fn (int $i) => $keys[$i];

        $page->set('data.km_arrivee', 50060)
            ->set("data.sites.{$key(0)}.statut", 'livre')->set("data.sites.{$key(0)}.km", 50010)
            ->set("data.sites.{$key(1)}.statut", 'non_livre')->set("data.sites.{$key(1)}.raison", 'Route coupée')->set("data.sites.{$key(1)}.km", 50030)
            ->set('data.ravitaillement.litres', 30)->set('data.ravitaillement.station', 'Total Méagui')
            ->set('data.frais', ['f1' => ['type' => 'peage', 'montant' => 1500, 'beneficiaire' => 'Péage de Soubré']])
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['livre', 'non_livre', 'planifie'], LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('statut')->all());
        $sortie = $this->plan->fresh()->sortie;
        $this->assertSame([50000, 50060, 'terminee'], [$sortie->km_depart, $sortie->km_arrivee, $sortie->statut]);
        $this->assertSame(1, Ravitaillement::count());
        $this->assertSame(1, Expense::count());

        // la page se recharge sur l'état enregistré : le plein et les frais saisis sont vidés (pas de doublon au prochain enregistrement)
        $keys = array_keys($page->get('data.sites'));   // la page est rechargée : nouvelles clés de lignes
        $page->assertSet('data.ravitaillement.litres', null)->assertSet('data.frais', [])->assertSet("data.sites.{$keys[0]}.statut", 'livre');
        $page->call('save')->assertHasNoErrors();
        $this->assertSame([1, 1], [Ravitaillement::count(), Expense::count()]);
    }

    public function test_the_page_lists_the_runs_with_search_and_opens_one(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $old = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $this->plan->vehicle_id, 'circuit_id' => $this->plan->circuit_id, 'date_prevue' => '2025-07-03', 'validation_statut' => 'valide']);

        $page = Livewire::test(SaisieTournee::class)
            ->assertSee('Liste des tournées')->assertSee('14/10/2026')->assertSee('03/07/2025')->assertSee('CIRCUIT 5')
            ->set('search', '03/07/2025')->assertSee('03/07/2025')->assertDontSee('14/10/2026')
            ->set('search', 'introuvable')->assertSee('Aucune tournée trouvée.')
            ->set('search', '')->call('openTour', $old->id);

        $page->assertSet('data.plan_id', $old->id);
        $this->assertCount(3, $page->get('data.sites'));
    }

    public function test_one_date_at_the_top_applies_to_every_delivered_site(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $page = $this->page();
        $keys = array_keys($page->get('data.sites'));

        $page->assertSet('data.date', fn ($v) => str_starts_with((string) $v, '2026-10-14'));   // proposée : la date prévue
        $page->set('data.date', '2026-10-12')
            ->set("data.sites.{$keys[0]}.statut", 'livre')->set("data.sites.{$keys[0]}.km", 50010)
            ->set("data.sites.{$keys[1]}.statut", 'transit')->set("data.sites.{$keys[1]}.km", 50020)
            ->call('save')->assertHasNoErrors();

        $dates = LivraisonEspc::where('chronogramme_id', $this->plan->id)->where('statut', 'livre')->get()->map(fn ($l) => $l->date_livraison->toDateString())->unique()->all();
        $this->assertSame(['2026-10-12'], $dates);
    }

    public function test_errors_are_attached_to_the_right_site_field(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $page = $this->page();
        $keys = array_keys($page->get('data.sites'));
        $key = fn (int $i) => $keys[$i];

        $page
            ->set("data.sites.{$key(0)}.statut", 'livre')->set("data.sites.{$key(0)}.km", 50030)
            ->set("data.sites.{$key(1)}.statut", 'livre')->set("data.sites.{$key(1)}.km", 50020)
            ->set("data.sites.{$key(2)}.statut", 'non_livre')
            ->call('save')
            ->assertHasErrors(["data.sites.{$key(1)}.km", "data.sites.{$key(2)}.raison"]);

        $this->assertSame(3, LivraisonEspc::where('statut', 'planifie')->count());   // rien n'est enregistré sur un refus
    }

    public function test_a_different_price_needs_the_confirmation_toggle(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);

        $this->page()->set('data.ravitaillement.litres', 20)->set('data.ravitaillement.prix_unitaire', 760)
            ->call('save')->assertHasErrors(['data.ravitaillement.prix_confirme']);

        $this->page()->set('data.ravitaillement.litres', 20)->set('data.ravitaillement.prix_unitaire', 760)->set('data.ravitaillement.prix_confirme', true)
            ->call('save')->assertHasNoErrors();
        $this->assertSame(760.0, (float) Ravitaillement::sole()->prix_unitaire);
    }

    public function test_the_page_is_reserved_to_those_who_can_edit_the_planning(): void
    {
        $this->as(User::ROLE_SUPERVISEUR);   // lecture seule
        $this->assertFalse(SaisieTournee::canAccess());

        $this->as(User::ROLE_DISTRICT_MANAGER);
        $this->assertTrue(SaisieTournee::canAccess());
        $this->get("/admin/{$this->district->id}/saisie-tournee")->assertOk()->assertSee('Saisie d&#039;une tournée', false);
    }

    public function test_the_follow_up_page_links_to_the_tour_entry(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);

        $this->get("/admin/{$this->district->id}/suivi-circuits?date=2026-10-14")->assertOk()->assertSee('Saisir toute la tournée')
            ->assertSee("saisie-tournee?plan={$this->plan->id}", false);
    }
}
