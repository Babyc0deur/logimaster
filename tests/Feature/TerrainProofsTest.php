<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Domain\Mobile\SiteGeolocation;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Preuves de terrain : photo du compteur, position GPS du véhicule et kilométrage relevés à chaque livraison. */
class TerrainProofsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private District $district;

    private Chronogramme $plan;

    private array $h;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D55031', 'km_actuel' => 42000, 'type_carburant' => 'diesel']);
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 715, 'date_effet' => '2026-01-01']);
        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'CIRCUIT 5', 'point_depart' => 'DDS MEAGUI']);
        foreach (['CSR A', 'CSU B', 'DR C'] as $i => $nom) {
            $circuit->espc()->attach(Espc::create(['district_id' => $this->district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => 10]);
        }
        $this->plan = Chronogramme::create([
            'district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'circuit_id' => $circuit->id,
            'driver_id' => Driver::create(['district_id' => $this->district->id, 'matricule' => 'CH1', 'nom_complet' => 'Conducteur'])->id,
            'date_prevue' => '2026-10-14', 'heure_depart' => '07:30', 'validation_statut' => 'soumis',
        ]);
        $chef = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KONE IBRAHIM', 'fonction' => 'chef_mission']);
        $this->plan->personnels()->attach([$chef->id]);
        app(ChronogrammeWorkflow::class)->validate($this->district->id, CarbonImmutable::parse('2026-10-01'), User::factory()->create(['is_active' => true]));

        $chef->fresh()->user->forceFill(['email' => 'ibrahim@exemple.org', 'password' => 'Secret123', 'must_change_password' => false, 'is_active' => true])->save();
        $this->app['auth']->forgetGuards();
        $this->h = ['Authorization' => 'Bearer '.$this->postJson('/api/mobile/login', ['email' => 'ibrahim@exemple.org', 'password' => 'Secret123'])->assertOk()->json('token')];
        $this->app['auth']->forgetGuards();
    }

    private function photo(): string
    {
        return 'data:image/png;base64,'.self::PNG;
    }

    private function stop(string $nom): LivraisonEspc
    {
        return LivraisonEspc::where('chronogramme_id', $this->plan->id)->whereHas('espc', fn ($q) => $q->where('nom', $nom))->firstOrFail();
    }

    public function test_odometer_photos_are_stored_at_start_and_finish_and_replays_do_not_duplicate(): void
    {
        $url = "/api/mobile/sorties/{$this->plan->id}";
        $this->postJson("$url/start", ['km_depart' => 42010, 'photo_compteur' => $this->photo()], $this->h)->assertOk()->assertJsonPath('sortie.photo_depart', true);
        $sortie = $this->plan->fresh()->sortie;
        $this->assertStringStartsWith('compteurs/', $sortie->photo_km_depart);
        Storage::disk('local')->assertExists($sortie->photo_km_depart);

        $this->postJson("$url/start", ['km_depart' => 42010, 'photo_compteur' => $this->photo()], $this->h)->assertOk();   // envoi rejoué
        $this->assertSame($sortie->photo_km_depart, $sortie->fresh()->photo_km_depart);
        $this->assertCount(1, Storage::disk('local')->files('compteurs'));

        $this->postJson("$url/finish", ['km_arrivee' => 42090, 'photo_compteur' => $this->photo()], $this->h)->assertOk()->assertJsonPath('sortie.photo_arrivee', true);
        $this->assertStringStartsWith('compteurs/', $sortie->fresh()->photo_km_arrivee);
    }

    public function test_delivery_records_the_vehicle_position_and_odometer_and_first_precise_position_places_the_centre(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010], $this->h)->assertOk();
        $stop = $this->stop('CSR A');

        $this->postJson("/api/mobile/livraisons/{$stop->id}", [
            'statut' => 'livre', 'lat' => 5.40012, 'lon' => -6.55031, 'precision' => 18, 'km_compteur' => 42035,
        ], $this->h)->assertOk()->assertJsonPath('stops.0.km', 42035)->assertJsonPath('stops.0.position', true);

        $stop->refresh();
        $this->assertSame(42035, $stop->km_compteur);
        $this->assertEqualsWithDelta(5.40012, $stop->lat, 1e-6);
        $this->assertSame(18, $stop->gps_precision_m);
        $this->assertSame(0, $stop->gps_ecart_m);
        $espc = $stop->espc->fresh();
        $this->assertEqualsWithDelta(5.40012, $espc->gps_lat, 1e-6);
        $this->assertSame('livraison', $espc->gps_source);
        $this->assertNotNull($espc->gps_releve_at);
    }

    public function test_odometer_below_the_departure_reading_is_refused(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010], $this->h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$this->stop('CSR A')->id}", ['statut' => 'livre', 'km_compteur' => 42000], $this->h)->assertStatus(422);
    }

    public function test_known_centre_keeps_its_position_and_a_distant_delivery_is_flagged(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->h)->assertOk();
        $stop = $this->stop('CSU B');
        $stop->espc->update(['gps_lat' => 5.40, 'gps_lon' => -6.55, 'gps_source' => 'manuel']);

        // environ 2,2 km au nord du centre
        $this->postJson("/api/mobile/livraisons/{$stop->id}", ['statut' => 'livre', 'lat' => 5.42, 'lon' => -6.55, 'precision' => 10], $this->h)->assertOk();

        $stop->refresh();
        $this->assertGreaterThan(2000, $stop->gps_ecart_m);
        $this->assertTrue(SiteGeolocation::isSuspicious($stop));
        $this->assertEqualsWithDelta(5.40, $stop->espc->fresh()->gps_lat, 1e-9);   // position du centre inchangée
    }

    public function test_imprecise_or_transit_positions_never_place_a_centre(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->h)->assertOk();
        $a = $this->stop('CSR A');
        $b = $this->stop('DR C');

        $this->postJson("/api/mobile/livraisons/{$a->id}", ['statut' => 'livre', 'lat' => 5.4, 'lon' => -6.5, 'precision' => 900], $this->h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$b->id}", ['statut' => 'transit', 'lat' => 5.4, 'lon' => -6.5, 'precision' => 5], $this->h)->assertOk();

        $this->assertNull($a->espc->fresh()->gps_lat);
        $this->assertNull($b->espc->fresh()->gps_lat);
        $this->assertSame(900, $a->fresh()->gps_precision_m);
    }

    public function test_marking_not_delivered_clears_the_odometer(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->h)->assertOk();
        $stop = $this->stop('CSR A');
        $this->postJson("/api/mobile/livraisons/{$stop->id}", ['statut' => 'livre', 'km_compteur' => 42100], $this->h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$stop->id}", ['statut' => 'non_livre', 'raison' => 'Centre fermé'], $this->h)->assertOk();

        $this->assertNull($stop->fresh()->km_compteur);
    }

    public function test_office_sees_the_odometer_the_position_and_the_position_alert(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010, 'photo_compteur' => $this->photo()], $this->h)->assertOk();
        $a = $this->stop('CSR A');
        $b = $this->stop('CSU B');
        $b->espc->update(['gps_lat' => 5.40, 'gps_lon' => -6.55]);
        $this->postJson("/api/mobile/livraisons/{$a->id}", ['statut' => 'livre', 'km_compteur' => 42050, 'lat' => 5.3, 'lon' => -6.5, 'precision' => 12], $this->h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$b->id}", ['statut' => 'livre', 'km_compteur' => 42080, 'lat' => 5.45, 'lon' => -6.55, 'precision' => 12], $this->h)->assertOk();

        $office = User::factory()->create(['is_active' => true]);
        $office->assignRole(User::ROLE_PRES_ADMIN);
        $this->app['auth']->forgetGuards();
        $this->actingAs($office, 'web');
        \Filament\Facades\Filament::setTenant($this->district);

        $this->get("/admin/{$this->district->id}/suivi-circuits?date=2026-10-14")->assertOk()
            ->assertSee('Compteur départ')->assertSee('42 010 km')->assertSee('+40 km')->assertSee('Kilométrage parcouru')
            ->assertSee('42 050 km')->assertSee('Position du véhicule relevée')
            ->assertSee('du centre au moment de la livraison');   // M. Yao : ~5,6 km du centre

        $this->get("/admin/{$this->district->id}/livraisons")->assertOk();
        $this->assertNotNull(\App\Support\PrivatePhoto::dataUri($this->plan->fresh()->sortie->photo_km_depart));
    }

    public function test_old_app_versions_without_proof_fields_still_work(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010], $this->h)->assertOk()->assertJsonPath('sortie.photo_depart', false);
        $this->postJson("/api/mobile/livraisons/{$this->stop('CSR A')->id}", ['statut' => 'livre', 'lat' => 5.4, 'lon' => -6.5], $this->h)->assertOk()->assertJsonPath('stops.0.etat', 'livre');
    }

    public function test_other_expenses_are_recorded_from_the_phone_once_and_listed_with_the_trip(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010], $this->h)->assertOk();
        $url = "/api/mobile/sorties/{$this->plan->id}/frais";
        $body = ['client_ref' => 'frais-001', 'type' => 'peage', 'montant' => 1500, 'beneficiaire' => 'Péage de Soubré', 'commentaire' => '=HYPERLINK("x")'];

        $this->postJson($url, $body, $this->h)->assertCreated()->assertJsonPath('libelle', 'Péage')->assertJsonPath('montant', 1500);
        $this->postJson($url, $body, $this->h)->assertOk();   // envoi rejoué : aucun doublon
        $this->assertSame(1, \App\Models\Expense::where('client_ref', 'frais-001')->count());

        $expense = \App\Models\Expense::where('client_ref', 'frais-001')->first();
        $this->assertSame($this->plan->fresh()->sortie_id, $expense->sortie_id);
        $this->assertSame($this->plan->vehicle_id, $expense->vehicle_id);
        $this->assertSame($this->district->id, $expense->district_id);
        $this->assertNotNull($expense->saisi_par);

        $this->getJson("/api/mobile/sorties/{$this->plan->id}", $this->h)->assertOk()->assertJsonPath('frais.0.beneficiaire', 'Péage de Soubré');
    }

    public function test_expense_validation_and_start_required(): void
    {
        $url = "/api/mobile/sorties/{$this->plan->id}/frais";
        $this->postJson($url, ['client_ref' => 'a', 'type' => 'peage', 'montant' => 500], $this->h)->assertStatus(409);   // circuit non démarré

        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->h)->assertOk();
        $this->postJson($url, ['client_ref' => 'b', 'type' => 'inconnu', 'montant' => 500], $this->h)->assertStatus(422);
        $this->postJson($url, ['client_ref' => 'c', 'type' => 'peage', 'montant' => 0], $this->h)->assertStatus(422);
        $this->postJson($url, ['type' => 'peage', 'montant' => 500], $this->h)->assertStatus(422);   // référence obligatoire
    }

    public function test_fuel_keeps_the_invoice_number_entered_on_the_phone(): void
    {
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->h)->assertOk();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'plein-1', 'litres' => 40, 'numero_facture' => 'F-2026-118'], $this->h)
            ->assertCreated()->assertJsonPath('numero_facture', 'F-2026-118');
    }
}
