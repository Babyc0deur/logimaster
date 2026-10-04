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
}
