<?php

namespace Tests\Feature;

use App\Filament\Pages\SuiviCircuits;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuiviCircuitsTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Chronogramme $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));

        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'Circuit Nord', 'point_depart' => 'DDKM']);
        foreach ([['CSR A', 1.5], ['CSU B', 14]] as $i => [$nom, $km]) {
            $circuit->espc()->attach(Espc::create(['district_id' => $this->district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => $km]);
        }
        $vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'AAA', 'km_actuel' => 1]);
        $this->plan = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'circuit_id' => $circuit->id, 'date_prevue' => '2026-09-10']);
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

    public function test_page_shows_the_circuit_as_a_line_of_stops(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $this->get("/admin/{$this->district->id}/suivi-circuits")->assertOk()
            ->assertSee('Circuit Nord')->assertSee('CSR A')->assertSee('CSU B')->assertSee('DDKM')->assertSee('0 / 2')->assertSee('mountAction', false);
    }

    public function test_marking_deliveries_updates_the_line_and_indicators(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        [$a, $b] = LivraisonEspc::orderBy('ordre')->get()->all();

        Livewire::test(SuiviCircuits::class)
            ->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site'], ['id' => $a->id])->assertHasNoActionErrors()
            ->callAction(['nonLivre'], ['raison' => 'Route inondée'], ['id' => $b->id])->assertHasNoActionErrors()
            ->assertSee('1 / 2')->assertSee('Route inondée');

        $this->assertSame('livre', $a->fresh()->statut);
        $this->assertSame('non_livre', $b->fresh()->statut);

        Livewire::test(SuiviCircuits::class)->callAction(['reinit'], [], ['id' => $b->id]);
        $this->assertSame('planifie', $b->fresh()->statut);
        $this->assertNull($b->fresh()->raison_non_livraison);
    }

    public function test_circuit_starts_and_ends_at_the_district(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $this->plan->circuit->update(['point_depart' => null]);
        $page = $this->get("/admin/{$this->district->id}/suivi-circuits")->assertOk();
        $page->assertSee('Départ')->assertSee('Retour');
        $this->assertSame(2, substr_count($page->getContent(), '<strong>D1</strong>'));
        $page->assertDontSee('tournée terminée');

        LivraisonEspc::query()->update(['statut' => 'livre', 'date_livraison' => '2026-09-10', 'lieu_livraison' => 'site']);
        $this->get("/admin/{$this->district->id}/suivi-circuits")->assertSee('tournée terminée');
    }

    public function test_circuit_page_shows_the_same_line_from_and_back_to_the_district(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        $circuit = $this->plan->circuit;
        $this->get("/admin/{$this->district->id}/circuits/{$circuit->id}")->assertOk()
            ->assertSee('Départ du district')->assertSee('Retour au district')->assertSee('CSR A')->assertSee('CSU B')
            ->assertSee("1,5 km depuis l'étape précédente", false);
        $this->assertNull($circuit->fresh('espc')->distanceRetour()); // sans GPS, pas d'estimation

        $circuit->update(['depart_lat' => 5.5, 'depart_lon' => -4.0]);
        $circuit->espc->last()->update(['gps_lat' => 5.4, 'gps_lon' => -4.1]);
        $this->assertGreaterThan(0, $circuit->fresh('espc')->distanceRetour());
    }

    public function test_navigation_changes_the_day(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        Livewire::test(SuiviCircuits::class)->assertSee('Circuit Nord')
            ->call('nextDay')->assertSee('Aucune sortie planifiée')
            ->call('today')->assertSee('Circuit Nord');
    }

    public function test_read_only_roles_see_the_line_without_actions(): void
    {
        $this->as(User::ROLE_SUPERVISEUR);
        $l = LivraisonEspc::first();
        $this->get("/admin/{$this->district->id}/suivi-circuits")->assertOk()->assertSee('CSR A')->assertDontSee('mountAction', false);
        try {
            Livewire::test(SuiviCircuits::class)->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site'], ['id' => $l->id]);
        } catch (\Throwable) {
        }
        $this->assertSame('planifie', $l->fresh()->statut);
    }

    public function test_the_office_can_enter_the_mileage_at_each_step_without_the_phone(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        [$a, $b] = $this->plan->livraisons()->orderBy('ordre')->get()->all();

        // compteur de départ : démarre la sortie du circuit comme « Démarrer » sur le téléphone
        Livewire::test(SuiviCircuits::class)
            ->callAction(['departKm'], ['km_depart' => 1000], ['id' => $this->plan->id])->assertHasNoActionErrors()
            ->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site', 'km_compteur' => 1012], ['id' => $a->id])->assertHasNoActionErrors()
            ->callAction(['nonLivre'], ['raison' => 'Centre fermé', 'km_compteur' => 1030], ['id' => $b->id])->assertHasNoActionErrors();

        $sortie = $this->plan->fresh()->sortie;
        $this->assertNotNull($sortie);
        $this->assertSame(1000, $sortie->km_depart);
        $this->assertSame('en_cours', $sortie->statut);
        $this->assertSame(1012, $a->fresh()->km_compteur);
        $this->assertSame(1030, $b->fresh()->km_compteur);   // le compteur d'un site non livré est conservé

        // compteur d'arrivée : termine la sortie ; les sites déjà traités le restent, aucun autre n'est marqué livré
        Livewire::test(SuiviCircuits::class)->callAction(['arriveeKm'], ['km_arrivee' => 1045], ['id' => $this->plan->id])->assertHasNoActionErrors();
        $this->assertSame(1045, $sortie->fresh()->km_arrivee);
        $this->assertSame('terminee', $sortie->fresh()->statut);
        $this->assertSame('non_livre', $b->fresh()->statut);

        // la page affiche la distance réelle (45 km) à côté de la distance prévue
        $this->get("/admin/{$this->district->id}/suivi-circuits?date=2026-09-10")->assertOk()
            ->assertSee('Distance réelle parcourue')->assertSee('45 km')->assertSee('Distance prévue')->assertSee('Compteur départ')->assertSee('Compteur arrivée');
    }

    public function test_mileage_entered_at_the_office_is_checked_against_the_previous_reading(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        [$a, $b] = $this->plan->livraisons()->orderBy('ordre')->get()->all();
        Livewire::test(SuiviCircuits::class)
            ->callAction(['departKm'], ['km_depart' => 1000], ['id' => $this->plan->id])
            ->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site', 'km_compteur' => 1012], ['id' => $a->id]);

        // inférieur au relevé précédent : refusé, rien n'est enregistré
        Livewire::test(SuiviCircuits::class)->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site', 'km_compteur' => 1005], ['id' => $b->id]);
        $this->assertSame('planifie', $b->fresh()->statut);
        $this->assertNull($b->fresh()->km_compteur);

        // arrivée inférieure au départ : refusée
        Livewire::test(SuiviCircuits::class)->callAction(['arriveeKm'], ['km_arrivee' => 990], ['id' => $this->plan->id]);
        $this->assertNull($this->plan->fresh()->sortie->km_arrivee);
    }

    public function test_without_a_start_reading_the_distance_runs_from_the_first_to_the_last_reading(): void
    {
        $this->as(User::ROLE_DISTRICT_MANAGER);
        [$a, $b] = $this->plan->livraisons()->orderBy('ordre')->get()->all();
        Livewire::test(SuiviCircuits::class)
            ->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site', 'km_compteur' => 2000], ['id' => $a->id])
            ->callAction(['livre'], ['date_livraison' => '2026-09-10', 'lieu_livraison' => 'site', 'km_compteur' => 2018], ['id' => $b->id]);

        $this->get("/admin/{$this->district->id}/suivi-circuits?date=2026-09-10")->assertOk()->assertSee('Kilométrage parcouru')->assertSee('18 km')->assertSee('du 1er au dernier relevé');
    }
}
