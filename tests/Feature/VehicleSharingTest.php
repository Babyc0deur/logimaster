<?php

namespace Tests\Feature;

use App\Domain\Fleet\VehicleSharing;
use App\Filament\Resources\Mutualisations\Pages\CreateMutualisation;
use App\Filament\Resources\Mutualisations\Pages\ListMutualisations;
use App\Filament\Resources\Chronogrammes\Pages\CreateChronogramme;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Expense;
use App\Models\Immobilisation;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMutualisation;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Mutualisation d'un véhicule entre deux districts : les deux voient ce que le véhicule fait, chacun garde ses coûts. */
class VehicleSharingTest extends TestCase
{
    use RefreshDatabase;

    private District $a;   // propriétaire

    private District $b;   // utilisateur

    private District $c;   // tiers

    private Vehicle $shared;

    private Vehicle $other;

    private User $userA;

    private User $userB;

    private User $userC;

    private VehicleMutualisation $loan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $mk = fn (string $n) => District::create(['region_id' => $region->id, 'name' => $n, 'sync_id' => $n, 'sync_password_hash' => 'x']);
        [$this->a, $this->b, $this->c] = [$mk('MEAGUI'), $mk('SOUBRE'), $mk('GUEYO')];
        $this->shared = Vehicle::create(['district_id' => $this->a->id, 'immatriculation' => 'D55031', 'marque' => 'FORD', 'modele' => 'TRANSIT', 'km_actuel' => 50000]);
        $this->other = Vehicle::create(['district_id' => $this->a->id, 'immatriculation' => 'D55041']);
        $this->userA = $this->manager($this->a);
        $this->userB = $this->manager($this->b);
        $this->userC = $this->manager($this->c);
        $this->loan = VehicleMutualisation::create(['vehicle_id' => $this->shared->id, 'district_id' => $this->b->id, 'date_debut' => '2026-10-10', 'motif' => 'Appui enlèvements']);
    }

    private function manager(District $d): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole(User::ROLE_DISTRICT_MANAGER);
        $u->districts()->attach($d->id);

        return $u;
    }

    /** Une journée d'activité du véhicule partagé, saisie par le district indiqué. */
    private function day(District $d, string $date, int $km, float $litres = 0, float $frais = 0): void
    {
        SortieVehicule::create(['district_id' => $d->id, 'vehicle_id' => $this->shared->id, 'km_depart' => 50000, 'km_arrivee' => 50000 + $km, 'motif' => 'distribution', 'statut' => 'terminee', 'date_sortie' => $date]);
        $litres > 0 && Ravitaillement::create(['district_id' => $d->id, 'vehicle_id' => $this->shared->id, 'litres' => $litres, 'prix_unitaire' => 725, 'date_ravitaillement' => $date]);
        $frais > 0 && Expense::create(['district_id' => $d->id, 'vehicle_id' => $this->shared->id, 'type' => 'peage', 'montant' => $frais, 'date_depense' => $date]);
    }

    private function activity(User $u, string $from = '2026-09-01', string $to = '2026-10-31'): array
    {
        return app(VehicleSharing::class)->activity($u, $this->shared->fresh(), CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    private function asTenant(User $u, District $d): void
    {
        $this->actingAs($u, 'web');
        Filament::setTenant($d);
    }

    public function test_the_borrowing_district_has_the_vehicle_in_its_list_and_choices(): void
    {
        $this->asTenant($this->userB, $this->b);

        $this->assertSame(['D55031'], Vehicle::usableBy($this->b->id)->pluck('immatriculation')->all());   // le prêté, pas les autres véhicules du propriétaire
        // portée appliquée par Filament aux requêtes du district affiché (VehicleResource)
        $scoped = fn (District $d) => VehicleResource::scopeEloquentQueryToTenant(Vehicle::withoutGlobalScopes(), $d)->orderBy('immatriculation')->pluck('immatriculation')->all();
        $this->assertSame(['D55031'], $scoped($this->b));

        $this->asTenant($this->userC, $this->c);
        $this->assertSame([], $scoped($this->c));          // un tiers ne voit rien

        $this->asTenant($this->userA, $this->a);
        $this->assertSame(['D55031', 'D55041'], $scoped($this->a));                                     // le propriétaire garde les siens
    }

    public function test_the_borrowed_vehicle_is_read_only_for_the_borrower(): void
    {
        $this->asTenant($this->userB, $this->b);
        $this->assertTrue($this->userB->can('view', $this->shared));
        $this->assertFalse($this->userB->can('update', $this->shared));
        $this->assertFalse($this->userB->can('delete', $this->shared));

        $this->asTenant($this->userA, $this->a);
        $this->assertTrue($this->userA->can('update', $this->shared));
    }

    public function test_a_run_can_be_planned_with_the_borrowed_vehicle_only_during_the_loan(): void
    {
        $this->asTenant($this->userB, $this->b);
        Chronogramme::creating(fn (Chronogramme $c) => $c->district_id ??= $this->b->id);

        Livewire::test(CreateChronogramme::class)
            ->fillForm(['date_prevue' => '2026-10-20', 'vehicle_id' => $this->shared->id, 'motif' => 'distribution'])
            ->call('create')->assertHasNoFormErrors();
        $plan = Chronogramme::withoutGlobalScopes()->where('vehicle_id', $this->shared->id)->firstOrFail();
        $this->assertSame($this->b->id, $plan->district_id);          // la sortie reste au district qui l'utilise
        $this->assertSame('D55031', $plan->vehicle->immatriculation);  // et le véhicule prêté reste lisible

        Livewire::test(CreateChronogramme::class)
            ->fillForm(['date_prevue' => '2026-10-05', 'vehicle_id' => $this->shared->id, 'motif' => 'distribution'])   // avant le début du prêt
            ->call('create')->assertHasFormErrors(['vehicle_id']);

        $this->assertTrue(app(VehicleSharing::class)->usableOn($this->b->id, $this->shared, '2026-10-20'));
        $this->assertFalse(app(VehicleSharing::class)->usableOn($this->b->id, $this->shared, '2026-10-05'));
        $this->assertFalse(app(VehicleSharing::class)->usableOn($this->c->id, $this->shared, '2026-10-20'));
    }

    public function test_an_ended_loan_no_longer_offers_the_vehicle_but_keeps_past_runs_readable(): void
    {
        $this->loan->update(['date_fin' => '2026-10-20']);
        $this->day($this->b, '2026-10-15', 40);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00'));

        $this->asTenant($this->userB, $this->b);
        $this->assertSame([], Vehicle::usableBy($this->b->id)->pluck('immatriculation')->all());
        $this->assertSame('D55031', SortieVehicule::withoutGlobalScopes()->firstOrFail()->vehicle->immatriculation);
    }

    public function test_who_can_see_the_vehicle(): void
    {
        $sharing = app(VehicleSharing::class);

        $this->assertTrue($sharing->canView($this->userA, $this->shared));   // propriétaire
        $this->assertTrue($sharing->canView($this->userB, $this->shared));   // district utilisateur
        $this->assertFalse($sharing->canView($this->userC, $this->shared));  // tiers
        $this->assertFalse($sharing->canView($this->userB, $this->other));   // les autres véhicules du propriétaire restent invisibles

        $national = User::factory()->create(['is_active' => true]);
        $national->assignRole(User::ROLE_PRES_ADMIN);
        $this->assertTrue($sharing->canView($national, $this->other));
    }

    public function test_the_owner_sees_everything_and_the_borrower_only_the_loan_period(): void
    {
        $this->day($this->a, '2026-10-05', 40, 10);          // avant la mise à disposition : chez le propriétaire
        $this->day($this->a, '2026-10-12', 60, 20, 500);     // pendant : sortie du propriétaire
        $this->day($this->b, '2026-10-14', 80, 30, 1500);    // pendant : sortie de l'utilisateur
        Immobilisation::create(['district_id' => $this->a->id, 'vehicle_id' => $this->shared->id, 'date_debut' => '2026-10-20', 'motif' => 'panne', 'montant' => 25000]);
        Vidange::create(['district_id' => $this->a->id, 'vehicle_id' => $this->shared->id, 'date' => '2026-09-20', 'km' => 49000, 'type' => 'simple', 'montant' => 30000]);

        $owner = $this->activity($this->userA);
        $this->assertCount(3, $owner['sorties']);
        $this->assertCount(2, $owner['entretiens']);
        $this->assertEqualsCanonicalizing(['MEAGUI', 'SOUBRE'], collect($owner['totaux'])->pluck('district')->all());

        $borrower = $this->activity($this->userB);
        $this->assertCount(2, $borrower['sorties']);                                   // pas la sortie du 5 octobre
        $this->assertCount(1, $borrower['entretiens']);                                // l'immobilisation du 20 octobre, pas la vidange de septembre
        $this->assertSame(['2026-10-14', '2026-10-12'], $borrower['sorties']->map(fn ($s) => $s->date_sortie->toDateString())->all());

        $this->assertSame([], $this->activity($this->userC)['sorties']->all());       // le tiers ne voit rien (et n'a pas accès à la page)
    }

    public function test_each_district_keeps_its_own_costs(): void
    {
        $this->day($this->a, '2026-10-12', 60, 20, 500);
        $this->day($this->b, '2026-10-14', 80, 30, 1500);

        $totals = collect($this->activity($this->userA)['totaux'])->keyBy('district');

        $this->assertSame([1, 60, 20 * 725.0, 500.0], [$totals['MEAGUI']['sorties'], $totals['MEAGUI']['km'], $totals['MEAGUI']['carburant'], $totals['MEAGUI']['frais']]);
        $this->assertSame([1, 80, 30 * 725.0, 1500.0], [$totals['SOUBRE']['sorties'], $totals['SOUBRE']['km'], $totals['SOUBRE']['carburant'], $totals['SOUBRE']['frais']]);
        $this->assertSame(500 + 20 * 725.0, $totals['MEAGUI']['total']);
    }

    public function test_an_ended_loan_stops_at_its_end_date_but_stays_in_the_history(): void
    {
        $this->day($this->b, '2026-10-14', 80);
        $this->day($this->b, '2026-10-25', 50);          // après la fin : ne doit plus être visible du district utilisateur
        $this->loan->update(['statut' => 'terminee', 'date_fin' => '2026-10-20']);

        $this->assertSame('terminee', $this->loan->fresh()->etat());
        $this->assertCount(1, $this->activity($this->userB)['sorties']);
        $this->assertCount(2, $this->activity($this->userA)['sorties']);       // le propriétaire voit tout
    }

    public function test_a_period_cannot_overlap_another_loan_of_the_same_vehicle(): void
    {
        $this->assertTrue(VehicleMutualisation::overlaps($this->shared->id, '2026-11-01', null));            // la première est sans fin
        $this->loan->update(['date_fin' => '2026-10-20']);
        $this->assertTrue(VehicleMutualisation::overlaps($this->shared->id, '2026-10-15', '2026-10-25'));
        $this->assertFalse(VehicleMutualisation::overlaps($this->shared->id, '2026-10-21', null));
        $this->assertFalse(VehicleMutualisation::overlaps($this->other->id, '2026-10-15', null));
        $this->assertFalse(VehicleMutualisation::overlaps($this->shared->id, '2026-10-15', '2026-10-25', $this->loan->id));   // en modifiant la même
    }

    public function test_only_the_owner_manages_the_loan(): void
    {
        $this->assertTrue($this->userA->can('update', $this->loan));
        $this->assertTrue($this->userA->can('delete', $this->loan));
        $this->assertFalse($this->userB->can('update', $this->loan));    // l'utilisateur la voit sans pouvoir la modifier
        $this->assertTrue($this->userB->can('view', $this->loan));
        $this->assertFalse($this->userC->can('view', $this->loan));
    }

    public function test_the_list_shows_lent_and_borrowed_vehicles_to_the_right_district(): void
    {
        $this->actingAs($this->userA, 'web');
        Filament::setTenant($this->a);
        Livewire::test(ListMutualisations::class)->assertCanSeeTableRecords([$this->loan])->assertSee('Prêté')->assertSee('D55031')->assertSee('SOUBRE');

        $this->actingAs($this->userB, 'web');
        Filament::setTenant($this->b);
        Livewire::test(ListMutualisations::class)->assertCanSeeTableRecords([$this->loan])->assertSee('Emprunté');

        $this->actingAs($this->userC, 'web');
        Filament::setTenant($this->c);
        Livewire::test(ListMutualisations::class)->assertCanNotSeeTableRecords([$this->loan]);
    }

    public function test_the_owner_lends_a_vehicle_through_the_form_with_checks(): void
    {
        $this->actingAs($this->userA, 'web');
        Filament::setTenant($this->a);
        $period = ['date_debut' => '2026-10-10'];

        // le véhicule est déjà prêté sur cette période
        Livewire::test(CreateMutualisation::class)->fillForm(['vehicle_id' => $this->shared->id, 'district_id' => $this->c->id] + $period)
            ->call('create')->assertHasFormErrors(['date_debut']);

        // un véhicule libre : prêté à GUEYO
        Livewire::test(CreateMutualisation::class)->fillForm(['vehicle_id' => $this->other->id, 'district_id' => $this->c->id, 'date_debut' => '2026-11-01', 'date_fin' => '2026-11-30', 'motif' => 'Campagne'])
            ->call('create')->assertHasNoFormErrors();
        $created = VehicleMutualisation::where('vehicle_id', $this->other->id)->sole();
        $this->assertSame(['active', $this->c->id, $this->userA->id], [$created->statut, $created->district_id, $created->cree_par]);

        // la date de fin ne peut pas précéder le début ; un district ne se prête pas à lui-même
        Livewire::test(CreateMutualisation::class)->fillForm(['vehicle_id' => $this->other->id, 'district_id' => $this->c->id, 'date_debut' => '2026-12-10', 'date_fin' => '2026-12-01'])
            ->call('create')->assertHasFormErrors(['date_fin']);
        Livewire::test(CreateMutualisation::class)->fillForm(['vehicle_id' => $this->other->id, 'district_id' => $this->a->id, 'date_debut' => '2026-12-10'])
            ->call('create')->assertHasFormErrors(['district_id']);
    }

    public function test_the_activity_page_is_open_to_both_districts_and_closed_to_the_others(): void
    {
        $this->day($this->b, '2026-10-14', 80, 30, 1500);
        $url = fn (District $d) => "/admin/{$d->id}/activite-vehicule?vehicule={$this->shared->id}";

        $this->actingAs($this->userA, 'web');
        $this->get($url($this->a))->assertOk()->assertSee('D55031')->assertSee('SOUBRE')->assertSee('Appui enlèvements');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->userB, 'web');
        $this->get($url($this->b))->assertOk()->assertSee('D55031')->assertSee('MEAGUI')->assertSee('pendant ces périodes seulement');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->userC, 'web');
        $this->get($url($this->c))->assertForbidden();
    }

    public function test_the_borrower_sees_the_lent_vehicle_in_the_list_even_with_the_district_scope_of_a_real_request(): void
    {
        // requête HTTP complète : Filament limite alors chaque modèle au district affiché, ce qui cacherait le véhicule d'un autre district
        $this->actingAs($this->userB, 'web');
        $this->get("/admin/{$this->b->id}/mutualisations")->assertOk()->assertSee('D55031')->assertSee('MEAGUI')->assertSee('Emprunté');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->userA, 'web');
        $this->get("/admin/{$this->a->id}/mutualisations")->assertOk()->assertSee('D55031')->assertSee('SOUBRE')->assertSee('Prêté');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->userC, 'web');
        $this->get("/admin/{$this->c->id}/mutualisations")->assertOk()->assertDontSee('D55031');
    }
}
