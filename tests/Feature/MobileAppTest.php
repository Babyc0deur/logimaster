<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Application mobile du bureau : authentification des comptes de l'administration, sorties du district, livraisons, carburant. */
class MobileAppTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private District $otherDistrict;

    private Vehicle $vehicle;

    private Chronogramme $plan;

    private Personnel $chef;

    /** Gestionnaire du district MEAGUI : utilise l'application mobile. */
    private User $manager;

    /** Gestionnaire d'un autre district : ne doit rien voir de MEAGUI. */
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->otherDistrict = District::create(['region_id' => $region->id, 'name' => 'SOUBRE', 'sync_id' => 'S', 'sync_password_hash' => 'x']);
        $this->vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D55031', 'km_actuel' => 42000, 'type_carburant' => 'diesel']);
        FuelPrice::query()->delete();
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 715, 'date_effet' => '2026-01-01']);

        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'CIRCUIT 5', 'point_depart' => 'DDS MEAGUI']);
        foreach ([['CSR A', 1.5], ['CSU B', 14], ['DR C', 22]] as $i => [$nom, $km]) {
            $circuit->espc()->attach(Espc::create(['district_id' => $this->district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => $km]);
        }
        $this->plan = Chronogramme::create([
            'district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'circuit_id' => $circuit->id,
            'driver_id' => Driver::create(['district_id' => $this->district->id, 'matricule' => 'CH1', 'nom_complet' => 'Conducteur'])->id,
            'date_prevue' => '2026-10-14', 'heure_depart' => '07:30',
        ]);
        $this->chef = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KONE IBRAHIM', 'fonction' => 'chef_mission']);
        $this->plan->personnels()->attach([$this->chef->id]);

        $this->manager = $this->account('gestionnaire@exemple.org', User::ROLE_DISTRICT_MANAGER, $this->district);
        $this->stranger = $this->account('autre@exemple.org', User::ROLE_DISTRICT_MANAGER, $this->otherDistrict);
    }

    private function account(string $email, string $role, ?District $district = null, bool $temporary = false): User
    {
        $u = User::factory()->create(['email' => $email, 'password' => 'Secret123', 'is_active' => true, 'must_change_password' => $temporary]);
        $u->assignRole($role);
        $district && $u->districts()->attach($district->id);

        return $u;
    }

    private function validated(): void
    {
        $this->plan->update(['validation_statut' => 'soumis']);
        app(ChronogrammeWorkflow::class)->validate($this->district->id, CarbonImmutable::parse('2026-10-01'), User::factory()->create(['is_active' => true]));
    }

    private function token(User $u): string
    {
        $this->app['auth']->forgetGuards();   // un nouvel appareil = une nouvelle requête authentifiée (sinon l'utilisateur précédent reste en mémoire)

        return $this->postJson('/api/mobile/login', ['email' => $u->email, 'password' => 'Secret123'])->assertOk()->json('token');
    }

    private function auth(User $u): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->token($u)];
        $this->app['auth']->forgetGuards();

        return $headers;
    }

    // ---------------------------------------------------------------- authentification

    public function test_login_returns_a_mobile_only_token_and_rejects_bad_credentials(): void
    {
        $this->postJson('/api/mobile/login', ['email' => $this->manager->email, 'password' => 'faux'])->assertStatus(422)->assertJsonValidationErrors('identifiant');
        $this->postJson('/api/mobile/login', ['email' => 'inconnu@exemple.org', 'password' => 'Secret123'])->assertStatus(422);

        $r = $this->postJson('/api/mobile/login', ['email' => $this->manager->email, 'password' => 'Secret123', 'device_name' => 'Tecno'])->assertOk();
        $r->assertJsonPath('user.fonction_label', 'Gestionnaire de district')->assertJsonPath('must_change_password', false)
            ->assertJsonCount(1, 'user.districts')->assertJsonPath('user.districts.0.name', 'MEAGUI');
        $this->assertNotEmpty($r->json('token'));
        $this->assertNotNull($this->manager->fresh()->last_mobile_login_at);

        // ce jeton ne donne accès à rien d'autre que l'application mobile
        $headers = ['Authorization' => 'Bearer '.$r->json('token')];
        $this->getJson('/api/vehicles', $headers)->assertForbidden();
        $this->getJson('/api/mobile/sorties', $headers)->assertOk();
    }

    public function test_only_active_office_accounts_with_the_permission_can_log_in(): void
    {
        $this->manager->update(['is_active' => false]);
        $this->postJson('/api/mobile/login', ['email' => $this->manager->email, 'password' => 'Secret123'])->assertStatus(422);

        // lecture seule (superviseur bailleur, responsable de région) : pas de saisie sur le terrain
        $viewer = $this->account('bailleur@exemple.org', User::ROLE_SUPERVISEUR, $this->district);
        $this->postJson('/api/mobile/login', ['email' => $viewer->email, 'password' => 'Secret123'])->assertStatus(422);

        // l'administrateur national peut se connecter
        $admin = $this->account('national@exemple.org', User::ROLE_PRES_ADMIN);
        $this->postJson('/api/mobile/login', ['email' => $admin->email, 'password' => 'Secret123'])->assertOk();

        // un jeton « classique » n'ouvre pas l'application mobile
        $classic = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'Secret123'])->json('token');
        $this->getJson('/api/mobile/sorties', ['Authorization' => 'Bearer '.$classic])->assertForbidden();
    }

    public function test_temporary_password_must_be_changed_before_anything_else(): void
    {
        $u = $this->account('nouveau@exemple.org', User::ROLE_DISTRICT_MANAGER, $this->district, true);
        $login = $this->postJson('/api/mobile/login', ['email' => $u->email, 'password' => 'Secret123'])->assertOk()->assertJsonPath('must_change_password', true);
        $h = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->getJson('/api/mobile/sorties', $h)->assertStatus(423)->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/mobile/me', $h)->assertOk();

        $this->postJson('/api/mobile/password', ['current_password' => 'faux', 'password' => 'NouveauCode2026', 'password_confirmation' => 'NouveauCode2026'], $h)->assertStatus(422);
        $this->postJson('/api/mobile/password', ['current_password' => 'Secret123', 'password' => 'court', 'password_confirmation' => 'court'], $h)->assertStatus(422);
        $this->postJson('/api/mobile/password', ['current_password' => 'Secret123', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'], $h)->assertStatus(422);
        $this->postJson('/api/mobile/password', ['current_password' => 'Secret123', 'password' => 'NouveauCode2026', 'password_confirmation' => 'NouveauCode2026'], $h)->assertOk();

        $this->getJson('/api/mobile/sorties', $h)->assertOk();
        $this->postJson('/api/mobile/login', ['email' => $u->email, 'password' => 'Secret123'])->assertStatus(422);
        $this->postJson('/api/mobile/login', ['email' => $u->email, 'password' => 'NouveauCode2026'])->assertOk()->assertJsonPath('must_change_password', false);
    }

    public function test_logout_revokes_the_token(): void
    {
        $h = $this->auth($this->manager);
        $this->assertSame(1, $this->manager->tokens()->count());

        $this->postJson('/api/mobile/logout', [], $h)->assertNoContent();
        $this->assertSame(0, $this->manager->tokens()->count());
    }

    public function test_the_mobile_app_has_no_push_notification_endpoints_any_more(): void
    {
        $h = $this->auth($this->manager);
        $this->getJson('/api/mobile/config', $h)->assertNotFound();
        $this->postJson('/api/mobile/push-subscriptions', [], $h)->assertNotFound();
        $this->postJson('/api/mobile/push-test', [], $h)->assertNotFound();
        $this->postJson('/api/mobile/vehicules/x/signalements', [], $h)->assertNotFound();
    }

    // ---------------------------------------------------------------- sorties du district

    public function test_only_validated_plans_of_my_district_are_visible(): void
    {
        $h = $this->auth($this->manager);
        $this->getJson('/api/mobile/sorties', $h)->assertOk()->assertJsonCount(0, 'data');   // pas encore validée

        $this->validated();
        $this->getJson('/api/mobile/sorties', $h)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.circuit', 'CIRCUIT 5')->assertJsonPath('data.0.sites', 3)->assertJsonPath('data.0.demarree', false);
        $this->getJson("/api/mobile/sorties/{$this->plan->id}", $h)->assertOk()
            ->assertJsonPath('depart', 'DDS MEAGUI')->assertJsonPath('stops.1.distance_km', 14)->assertJsonPath('equipe.0.nom', 'KONE IBRAHIM');

        // le gestionnaire d'un autre district ne voit rien, même en devinant l'identifiant
        $other = $this->auth($this->stranger);
        $this->getJson('/api/mobile/sorties', $other)->assertJsonCount(0, 'data');
        $this->getJson("/api/mobile/sorties/{$this->plan->id}", $other)->assertNotFound();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $other)->assertNotFound();
        $this->getJson("/api/mobile/sorties?district_id={$this->district->id}", $other)->assertForbidden();   // district hors de son périmètre
    }

    public function test_the_national_administrator_chooses_the_district(): void
    {
        $this->validated();
        $admin = $this->auth($this->account('national@exemple.org', User::ROLE_PRES_ADMIN));

        $this->getJson("/api/mobile/sorties?district_id={$this->district->id}", $admin)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/mobile/sorties?district_id={$this->otherDistrict->id}", $admin)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/mobile/sorties/{$this->plan->id}", $admin)->assertOk();   // une sortie reste accessible sans repréciser le district
        $this->getJson('/api/mobile/me', $admin)->assertJsonCount(2, 'user.districts');
    }

    public function test_cancelled_plans_are_hidden(): void
    {
        $this->validated();
        $this->plan->update(['statut' => 'annulee']);
        $this->getJson('/api/mobile/sorties', $this->auth($this->manager))->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------- exécution du circuit

    public function test_full_run_start_deliver_fuel_finish_and_it_shows_on_the_web(): void
    {
        $this->validated();
        $h = $this->auth($this->manager);
        [$a, $b, $c] = LivraisonEspc::orderBy('ordre')->get()->all();

        // on ne livre pas avant d'avoir démarré
        $this->postJson("/api/mobile/livraisons/{$a->id}", ['statut' => 'livre'], $h)->assertStatus(409);

        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", ['km_depart' => 42010], $h)->assertOk()
            ->assertJsonPath('demarree', true)->assertJsonPath('sortie.km_depart', 42010)->assertJsonPath('sortie.statut', 'en_cours');
        $this->assertSame($this->chef->id, SortieVehicule::first()->chef_mission_id);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $h)->assertOk();   // rejouable
        $this->assertSame(1, SortieVehicule::count());

        $this->postJson("/api/mobile/livraisons/{$a->id}", ['statut' => 'livre'], $h)->assertOk()->assertJsonPath('stops.0.etat', 'livre')->assertJsonPath('traites', 1);
        $this->postJson("/api/mobile/livraisons/{$b->id}", ['statut' => 'transit', 'commentaire' => 'centre fermé, remis à la mairie'], $h)->assertOk()->assertJsonPath('stops.1.etat', 'transit');
        $this->postJson("/api/mobile/livraisons/{$c->id}", ['statut' => 'non_livre'], $h)->assertStatus(422);   // raison obligatoire
        $this->postJson("/api/mobile/livraisons/{$c->id}", ['statut' => 'non_livre', 'raison' => 'Route coupée'], $h)->assertOk()->assertJsonPath('stops.2.raison', 'Route coupée');

        $fresh = $a->fresh();
        $this->assertSame('livre', $fresh->statut);
        $this->assertSame('site', $fresh->lieu_livraison);
        $this->assertSame($this->manager->id, $fresh->saisi_par);
        $this->assertNull($fresh->lat);   // aucune position GPS n'est relevée
        $this->assertSame('transit', $b->fresh()->lieu_livraison);

        // carburant pris pendant la sortie (rejouable grâce à client_ref)
        $fuel = ['client_ref' => 'tel-0001', 'litres' => 40, 'km_compteur' => 42100, 'station' => 'Total Méagui'];
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", $fuel, $h)->assertCreated()->assertJsonPath('prix_unitaire', 715)->assertJsonPath('montant', 28600);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", $fuel, $h)->assertOk();
        $this->assertSame(1, Ravitaillement::count());
        $r = Ravitaillement::first();
        $this->assertSame(SortieVehicule::first()->id, $r->sortie_id);
        $this->assertSame($this->vehicle->id, $r->vehicle_id);
        $this->assertSame($this->manager->id, $r->saisi_par);

        // clôture : le kilométrage ne peut pas reculer
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/finish", ['km_arrivee' => 42000], $h)->assertStatus(422);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/finish", ['km_arrivee' => 42130], $h)->assertOk()->assertJsonPath('sortie.statut', 'terminee')->assertJsonPath('terminee', true);
        $this->assertSame(42130, $this->vehicle->fresh()->km_actuel);

        // ce que voit le bureau : les statuts saisis sur le téléphone, rien de plus
        $this->assertSame(['livre', 'livre', 'non_livre'], LivraisonEspc::orderBy('ordre')->pluck('statut')->all());
    }

    public function test_finishing_does_not_mark_untreated_sites_as_delivered(): void
    {
        $this->validated();
        $h = $this->auth($this->manager);
        $first = LivraisonEspc::orderBy('ordre')->first();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$first->id}", ['statut' => 'livre'], $h)->assertOk();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/finish", ['km_arrivee' => 42050], $h)->assertOk()->assertJsonPath('restants', 2);

        $this->assertSame(2, LivraisonEspc::where('statut', 'planifie')->count());   // restent « à traiter » au bureau
    }

    public function test_offline_actions_keep_their_real_time_and_can_be_replayed(): void
    {
        $this->validated();
        $h = $this->auth($this->manager);
        $first = LivraisonEspc::orderBy('ordre')->first();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $h)->assertOk();

        $when = '2026-10-14T07:52:10+00:00';
        $this->postJson("/api/mobile/livraisons/{$first->id}", ['statut' => 'livre', 'done_at' => $when], $h)->assertOk();
        $this->postJson("/api/mobile/livraisons/{$first->id}", ['statut' => 'livre', 'done_at' => $when], $h)->assertOk();   // rejeu : même résultat
        $this->assertSame('2026-10-14', $first->fresh()->date_livraison->toDateString());
        $this->assertSame('07:52:10', $first->fresh()->saisi_at->utc()->format('H:i:s'));

        $this->postJson("/api/mobile/livraisons/{$first->id}", ['statut' => 'planifie'], $h)->assertOk();   // correction d'une erreur
        $this->assertSame('planifie', $first->fresh()->statut);
    }

    public function test_fuel_requires_a_started_run_and_a_price(): void
    {
        $this->validated();
        $h = $this->auth($this->manager);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'a', 'litres' => 10], $h)->assertStatus(409);

        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $h)->assertOk();
        FuelPrice::query()->delete();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'a', 'litres' => 10], $h)->assertStatus(422);
        // sans prix envoyé : celui du type de carburant du véhicule ; avec un prix (modifié et confirmé sur le téléphone) : celui-là
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 700, 'date_effet' => '2026-01-01']);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'a', 'litres' => 10], $h)->assertCreated()->assertJsonPath('prix_unitaire', 700);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'a2', 'litres' => 10, 'prix_unitaire' => 750], $h)->assertCreated()->assertJsonPath('prix_unitaire', 750);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'b', 'litres' => 0], $h)->assertStatus(422);
    }

    public function test_another_district_cannot_touch_these_deliveries(): void
    {
        $this->validated();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $this->auth($this->manager))->assertOk();
        $first = LivraisonEspc::orderBy('ordre')->first();
        $this->postJson("/api/mobile/livraisons/{$first->id}", ['statut' => 'livre'], $this->auth($this->stranger))->assertNotFound();
        $this->assertSame('planifie', $first->fresh()->statut);
    }

    // ---------------------------------------------------------------- photo de la facture de carburant

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function startedRun(): array
    {
        $this->validated();
        $h = $this->auth($this->manager);
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/start", [], $h)->assertOk();

        return $h;
    }

    public function test_fuel_invoice_photo_is_stored_privately_and_attached_to_the_fill(): void
    {
        Storage::fake('local');
        $h = $this->startedRun();

        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", [
            'client_ref' => 'tel-photo-1', 'litres' => 40, 'station' => 'Total Méagui', 'facture_photo' => 'data:image/png;base64,'.self::PNG,
        ], $h)->assertCreated()->assertJsonPath('facture', true);

        $fuel = Ravitaillement::where('client_ref', 'tel-photo-1')->first();
        $this->assertStringStartsWith('factures/', $fuel->facture_path);
        $this->assertStringEndsWith('.png', $fuel->facture_path);
        Storage::disk('local')->assertExists($fuel->facture_path);
        $this->assertSame(base64_decode(self::PNG), Storage::disk('local')->get($fuel->facture_path));
        $this->assertTrue($this->getJson("/api/mobile/sorties/{$this->plan->id}", $h)->json('ravitaillements.0.facture'));
    }

    public function test_a_fill_without_photo_stays_valid_and_a_replay_can_add_the_photo(): void
    {
        Storage::fake('local');
        $h = $this->startedRun();
        $url = "/api/mobile/sorties/{$this->plan->id}/ravitaillements";

        $this->postJson($url, ['client_ref' => 'tel-2', 'litres' => 20], $h)->assertCreated()->assertJsonPath('facture', false);
        // envoi rejoué avec la photo : rattachée au plein existant, pas de second plein
        $this->postJson($url, ['client_ref' => 'tel-2', 'litres' => 20, 'facture_photo' => 'data:image/png;base64,'.self::PNG], $h)->assertOk()->assertJsonPath('facture', true);
        $this->assertSame(1, Ravitaillement::count());
        // un nouveau rejeu ne crée pas de second fichier
        $this->postJson($url, ['client_ref' => 'tel-2', 'litres' => 20, 'facture_photo' => 'data:image/png;base64,'.self::PNG], $h)->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles('factures'));
    }

    public function test_only_real_small_images_are_accepted_as_invoice_photos(): void
    {
        Storage::fake('local');
        $h = $this->startedRun();
        $url = "/api/mobile/sorties/{$this->plan->id}/ravitaillements";
        $post = fn (string $ref, string $photo) => $this->postJson($url, ['client_ref' => $ref, 'litres' => 10, 'facture_photo' => $photo], $h);

        $post('a', 'data:image/png;base64,'.base64_encode('<?php echo "pas une image";'))->assertStatus(422);   // type déclaré faux : contrôlé sur le contenu
        $post('b', 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>'))->assertStatus(422);   // SVG refusé
        $post('c', 'data:application/pdf;base64,'.self::PNG)->assertStatus(422);
        $post('d', 'pas-une-data-url')->assertStatus(422);
        $post('e', 'data:image/jpeg;base64,'.base64_encode(str_repeat("\xFF", 5 * 1024 * 1024)))->assertStatus(422);   // trop lourde
        $this->assertSame(0, Ravitaillement::count());                                                                  // rien d'enregistré sur un refus
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_office_can_view_the_invoice_photo_of_a_fill(): void
    {
        Storage::fake('local');
        $h = $this->startedRun();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'tel-3', 'litres' => 30, 'facture_photo' => 'data:image/png;base64,'.self::PNG], $h)->assertCreated();
        $this->postJson("/api/mobile/sorties/{$this->plan->id}/ravitaillements", ['client_ref' => 'tel-4', 'litres' => 10], $h)->assertCreated();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        \Filament\Facades\Filament::setTenant($this->district);
        $withPhoto = Ravitaillement::where('client_ref', 'tel-3')->first();
        $without = Ravitaillement::where('client_ref', 'tel-4')->first();

        \Livewire\Livewire::test(\App\Filament\Resources\Ravitaillements\Pages\ListRavitaillements::class)
            ->assertTableActionVisible('voir_facture', $withPhoto)
            ->assertTableActionHidden('voir_facture', $without)
            ->assertTableActionVisible('facture', $withPhoto);
        $html = view('filament.modals.facture-image', ['src' => 'data:image/png;base64,'.self::PNG])->render();
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    // ---------------------------------------------------------------- notifications dans l'application

    public function test_in_app_notifications_can_be_read_and_marked_as_read(): void
    {
        $h = $this->auth($this->manager);
        \Filament\Notifications\Notification::make()->title('Chronogramme à valider')->body('3 sorties de octobre 2026')->sendToDatabase($this->manager);

        $n = $this->getJson('/api/mobile/notifications', $h)->assertOk()->assertJsonPath('unread', 1);
        $this->assertSame('Chronogramme à valider', $n->json('data.0.title'));
        $this->postJson('/api/mobile/notifications/read', [], $h)->assertNoContent();
        $this->getJson('/api/mobile/notifications', $h)->assertJsonPath('unread', 0);
    }

    public function test_office_accounts_keep_their_access_to_the_admin_panel(): void
    {
        $this->assertTrue($this->manager->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->manager->update(['is_active' => false]);
        $this->assertFalse($this->manager->fresh()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }
}
