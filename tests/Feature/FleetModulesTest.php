<?php

namespace Tests\Feature;

use App\Domain\Fleet\AlertLevel;
use App\Domain\Fleet\MaintenancePlanner;
use App\Domain\Fuel\FuelAnalyzer;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\FuelPrice;
use App\Models\Immobilisation;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\Setting;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetModulesTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
        $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    }

    private function user(string $role, array $districts = []): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->sync(array_map(fn ($d) => $d->id, $districts));
        Sanctum::actingAs($user);

        return $user;
    }

    private function vehicle(array $attrs = []): Vehicle
    {
        return Vehicle::create($attrs + ['district_id' => $this->d1->id, 'immatriculation' => 'V'.random_int(1000, 9999)]);
    }

    private function sortie(Vehicle $v, array $attrs = []): SortieVehicule
    {
        return SortieVehicule::create($attrs + [
            'district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'km_depart' => 1000, 'km_arrivee' => 1100,
            'motif' => 'distribution', 'statut' => 'terminee',
        ]);
    }

    private function refuel(Vehicle $v, float $litres, array $attrs = []): Ravitaillement
    {
        return Ravitaillement::create($attrs + ['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'litres' => $litres, 'prix_unitaire' => 700]);
    }

    // ---------- Module 5 : niveaux d'alerte ----------

    public function test_alert_levels_follow_the_plan_thresholds(): void
    {
        $this->assertSame(AlertLevel::Urgent, AlertLevel::forKm(99));
        $this->assertSame(AlertLevel::Attention, AlertLevel::forKm(100));
        $this->assertSame(AlertLevel::Attention, AlertLevel::forKm(499));
        $this->assertSame(AlertLevel::Ok, AlertLevel::forKm(500));
        $this->assertSame(AlertLevel::Urgent, AlertLevel::forDays(6));
        $this->assertSame(AlertLevel::Attention, AlertLevel::forDays(29));
        $this->assertSame(AlertLevel::Ok, AlertLevel::forDays(30));

        Setting::put('seuil_vidange_urgent_km', 200); // seuils paramétrables
        $this->assertSame(AlertLevel::Urgent, AlertLevel::forKm(150));
    }

    public function test_planner_alerts_are_sorted_by_gravity(): void
    {
        $this->vehicle(['immatriculation' => 'OIL1', 'km_actuel' => 9950, 'km_vidange' => 10000]);                       // urgent (50 km)
        $this->vehicle(['immatriculation' => 'CT1', 'date_ct' => '2026-09-30']);                                          // attention (15 j)
        $this->vehicle(['immatriculation' => 'INS1', 'date_assurance' => '2026-09-01']);                                  // expirée → urgent
        $this->vehicle(['immatriculation' => 'OK1', 'km_actuel' => 1000, 'km_vidange' => 9000, 'date_ct' => '2027-09-30']); // aucune alerte
        $broken = $this->vehicle(['immatriculation' => 'BRK1']);
        Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $broken->id, 'date_debut' => '2026-09-01', 'motif' => 'panne']);

        $alerts = app(MaintenancePlanner::class)->alerts([$this->d1->id]);

        $this->assertCount(4, $alerts);
        $this->assertSame(['urgent', 'urgent', 'urgent', 'attention'], $alerts->map(fn ($a) => $a['level']->value)->all());
        $this->assertTrue($alerts->contains(fn ($a) => $a['type'] === 'immobilisation' && str_contains($a['message'], '15 jours')));
        $this->assertTrue($alerts->contains(fn ($a) => str_contains($a['message'], 'expiré')));
        $this->assertSame([], app(MaintenancePlanner::class)->alerts([$this->d2->id])->all()); // cloisonnement
    }

    public function test_vidange_updates_vehicle_and_estimates_next_date_from_average_pace(): void
    {
        $v = $this->vehicle(['km_actuel' => 1000]);
        // 900 km sur les 90 derniers jours = 10 km/jour
        $this->sortie($v, ['km_depart' => 1000, 'km_arrivee' => 1900, 'date_sortie' => '2026-08-15']);
        $planner = app(MaintenancePlanner::class);

        $this->assertSame(10.0, $planner->averageKmPerDay($v));
        $this->assertSame('2026-09-25', $planner->estimateDateForKm($v, 1100, 1000)->toDateString()); // +100 km = 10 jours
        $this->assertNull($planner->estimateDateForKm($this->vehicle(), 5000));                          // aucun historique

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->postJson('/api/vidanges', [
            'district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date' => '2026-09-15', 'km' => 2000, 'type' => 'complete',
            'prochain_km' => 7000, 'prochaine_date' => '2027-01-10',
        ])->assertCreated();
        $v->refresh();
        $this->assertSame(7000, $v->km_vidange);
        $this->assertSame(2000, $v->km_actuel);
        $this->postJson('/api/vidanges', ['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date' => '2026-09-15', 'km' => 2100, 'type' => 'inconnu'])->assertStatus(422);
    }

    public function test_immobilisation_duration_status_and_calendar_events(): void
    {
        $v = $this->vehicle(['immatriculation' => 'CAL1', 'date_ct' => '2026-09-30']); // J-15 → attention
        $closed = Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-05', 'motif' => 'carrosserie', 'statut' => 'terminee']);
        $open = Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date_debut' => '2026-09-10', 'motif' => 'visite_technique', 'statut' => 'en_attente_pieces']);
        $this->assertSame(5, $closed->duree_jours);
        $this->assertSame(6, $open->duree_jours); // du 10 au 15 inclus

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->getJson('/api/maintenance/calendar?from=2026-09-01&to=2026-09-30')->assertOk()
            ->assertJsonPath('events.0.type', 'date_ct')->assertJsonPath('events.0.level', 'attention');
        $this->patchJson("/api/immobilisations/{$open->id}", ['statut' => 'terminee', 'motif' => 'autre'])->assertOk();
        $this->patchJson("/api/immobilisations/{$open->id}", ['statut' => 'resolu'])->assertStatus(422);
    }

    // ---------- Module 4 : carburant ----------

    public function test_fuel_analysis_flags_overconsumption_over_threshold(): void
    {
        $ok = $this->vehicle(['immatriculation' => 'OK', 'consommation_theorique' => 10]);
        $bad = $this->vehicle(['immatriculation' => 'BAD', 'consommation_theorique' => 10]);
        $s = [];
        foreach ([$ok, $bad] as $v) {
            $s[$v->id] = $this->sortie($v, ['km_depart' => 0, 'km_arrivee' => 100]);
        }
        $this->refuel($ok, 10.5, ['sortie_id' => $s[$ok->id]->id]);   // 10,5 L/100 → +5 %  → conforme
        $this->refuel($bad, 12, ['sortie_id' => $s[$bad->id]->id]);   // 12 L/100 → +20 % → surconsommation (> 15 %)

        $rows = app(FuelAnalyzer::class)->perVehicle([$this->d1->id], CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'))->keyBy(fn ($r) => $r['vehicle']->immatriculation);

        $this->assertEquals(5.0, $rows['OK']['ecart']);
        $this->assertFalse($rows['OK']['surconsommation']);
        $this->assertEquals(20.0, $rows['BAD']['ecart']);
        $this->assertTrue($rows['BAD']['surconsommation']);
        $this->assertEquals(84.0, $rows['BAD']['cout_km']); // 12 L × 700 / 100 km

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->getJson('/api/fuel/analysis?period=2026-09')->assertOk()
            ->assertJsonPath('totaux.vehicules_en_surconsommation', 1)
            ->assertJsonPath('par_motif.distribution.litres', 22.5)
            ->assertJsonCount(12, 'serie_mensuelle');
    }

    public function test_refuel_anomaly_detection_and_manager_validation(): void
    {
        $v = $this->vehicle(['consommation_theorique' => 10]);
        $first = $this->refuel($v, 40, ['km_compteur' => 1000]);
        $this->assertNull($first->anomalie);

        $this->assertSame('km_incoherent', $this->refuel($v, 30, ['km_compteur' => 900])->anomalie);       // compteur recule
        $this->travel(1)->hours();
        $this->assertSame('surconsommation', $this->refuel($v, 30, ['km_compteur' => 1100])->anomalie);    // 30 L sur 200 km (vs plein précédent 900 → 1100) = 15 L/100
        $this->travel(1)->hours();
        $this->assertNull($this->refuel($v, 21, ['km_compteur' => 1300])->anomalie);                        // 10,5 L/100 → ok

        $manager = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->postJson("/api/ravitaillements/{$first->id}/validate")->assertOk()->assertJsonPath('valide_par', $manager->id);
        $this->user(User::ROLE_SUPERVISEUR, [$this->d1]);
        $this->postJson("/api/ravitaillements/{$first->id}/validate")->assertForbidden();
    }

    public function test_fuel_prices_history_and_current_price(): void
    {
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 650, 'date_effet' => '2026-01-01']);
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 700, 'date_effet' => '2026-08-01']);
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 750, 'date_effet' => '2026-12-01']); // futur
        $this->assertSame(700.0, FuelPrice::current('diesel'));
        $this->assertSame(650.0, FuelPrice::current('diesel', '2026-03-01'));
        $this->assertNull(FuelPrice::current('essence'));

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->getJson('/api/fuel-prices')->assertOk()->assertJsonPath('en_vigueur.diesel', 700)->assertJsonCount(3, 'historique');
        $this->postJson('/api/fuel-prices', ['type_carburant' => 'essence', 'prix' => 800, 'date_effet' => '2026-09-01'])->assertForbidden();
        $this->putJson('/api/settings', ['seuil_surconsommation' => 10])->assertForbidden();

        $this->user(User::ROLE_PRES_ADMIN);
        $this->postJson('/api/fuel-prices', ['type_carburant' => 'essence', 'prix' => 800, 'date_effet' => '2026-09-01'])->assertCreated();
        $this->putJson('/api/settings', ['seuil_surconsommation' => 10])->assertOk()->assertJsonPath('seuil_surconsommation', 10);
        $this->assertSame(10, Setting::get('seuil_surconsommation'));
    }

    // ---------- Module 3 : sorties ----------

    public function test_sortie_full_form_costs_alert_validation_and_duplication(): void
    {
        $v = $this->vehicle(['km_actuel' => 5000, 'consommation_theorique' => 10]);
        $driver = Driver::create(['district_id' => $this->d1->id, 'matricule' => 'CH1', 'nom_complet' => 'X']);
        $chef = \App\Models\Personnel::create(['district_id' => $this->d1->id, 'nom_complet' => 'Chef', 'fonction' => 'chef_mission']);
        $p1 = \App\Models\Personnel::create(['district_id' => $this->d1->id, 'nom_complet' => 'P1']);
        $foreign = \App\Models\Personnel::create(['district_id' => $this->d2->id, 'nom_complet' => 'Autre district']);
        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);

        $payload = [
            'district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'driver_id' => $driver->id, 'chef_mission_id' => $chef->id,
            'date_sortie' => '2026-09-14', 'km_depart' => 5000, 'motif' => 'supervision', 'point_depart' => 'DS',
            'etapes' => [['lieu' => 'CSR A', 'km' => 5040]], 'passagers' => [$p1->id],
        ];
        $this->postJson('/api/sorties', ['motif' => 'inconnu'] + $payload)->assertStatus(422);
        $this->postJson('/api/sorties', ['chef_mission_id' => $foreign->id] + $payload)->assertStatus(422); // personnel d'un autre district
        $id = $this->postJson('/api/sorties', $payload)->assertCreated()->assertJsonPath('passagers.0.nom_complet', 'P1')->json('id');

        $this->patchJson("/api/sorties/{$id}", ['km_arrivee' => 4000])->assertStatus(422);  // arrivée ≤ départ
        $this->patchJson("/api/sorties/{$id}", ['km_arrivee' => 5100])->assertOk()->assertJsonPath('statut', 'terminee');

        // 15 L sur 100 km = 15 L/100 vs 10 théorique → +50 % → alerte (> 20 %)
        $sortie = SortieVehicule::find($id);
        Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'sortie_id' => $id, 'litres' => 15, 'prix_unitaire' => 700]);
        \App\Models\Expense::create(['district_id' => $this->d1->id, 'sortie_id' => $id, 'type' => 'collation', 'montant' => 5000]);

        $this->getJson("/api/sorties/{$id}")->assertOk()
            ->assertJsonPath('distance', 100)->assertJsonPath('cout_carburant', 10500)->assertJsonPath('cout_autres_frais', 5000)
            ->assertJsonPath('cout_total', 15500)->assertJsonPath('ecart_consommation_pct', 50)->assertJsonPath('alerte_surconsommation', true);

        $this->postJson("/api/sorties/{$id}", [])->assertStatus(405);
        $this->postJson("/api/sorties/{$id}/validate")->assertOk()->assertJsonPath('statut', 'validee');
        $this->patchJson("/api/sorties/{$id}", ['commentaires' => 'x'])->assertStatus(422); // verrouillée
        $this->postJson("/api/sorties/{$id}/validate")->assertStatus(422);

        $copyId = $this->postJson("/api/sorties/{$id}/duplicate", ['date_sortie' => '2026-09-22'])->assertCreated()
            ->assertJsonPath('statut', 'planifiee')->assertJsonPath('km_depart', 5100)->json('id');   // km actuel du véhicule
        $this->assertSame(1, SortieVehicule::find($copyId)->passagers()->count());
        $this->assertNull(SortieVehicule::find($copyId)->km_arrivee);

        $this->user(User::ROLE_SUPERVISEUR, [$this->d1]);
        $this->postJson("/api/sorties/{$copyId}/validate")->assertForbidden();
    }

    // ---------- Module 2 : véhicules ----------

    public function test_vehicle_new_fields_validation_and_stats(): void
    {
        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $base = ['district_id' => $this->d1->id, 'immatriculation' => 'D55032'];

        $this->postJson('/api/vehicles', ['immatriculation' => '!!'] + $base)->assertStatus(422);               // format invalide
        $this->postJson('/api/vehicles', ['annee_circulation' => 1950] + $base)->assertStatus(422);
        $this->postJson('/api/vehicles', ['date_reception' => '2030-01-01'] + $base)->assertStatus(422);        // dates cohérentes
        $this->postJson('/api/vehicles', ['appartenance' => 'inconnue'] + $base)->assertStatus(422);

        $id = $this->postJson('/api/vehicles', $base + [
            'marque' => 'FORD', 'type_vehicule' => 'fourgon', 'type_carburant' => 'diesel', 'consommation_theorique' => 15,
            'annee_circulation' => 2019, 'poids_vide' => 2100, 'capacite_charge' => 1500, 'volume_utile' => 9.5,
            'appartenance' => 'mutualisation', 'bailleur' => 'UCP FM', 'date_reception' => '2019-06-15', 'prix_carburant' => 700,
        ])->assertCreated()->assertJsonPath('bailleur', 'UCP FM')->assertJsonPath('appartenance', 'mutualisation')->json('id');

        $v = Vehicle::find($id);
        $this->sortie($v, ['km_depart' => 0, 'km_arrivee' => 200, 'date_sortie' => '2026-09-10', 'created_at' => '2026-09-10']);
        $this->refuel($v, 30);
        Vidange::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date' => '2026-09-12', 'km' => 200, 'montant' => 20000]);

        $this->getJson("/api/vehicles/{$id}/stats")->assertOk()
            ->assertJsonPath('km_parcourus', 200)->assertJsonPath('consommation_reelle_l_100km', 15)
            ->assertJsonPath('cout_km', 205)                                  // (30 L × 700 + 20 000) / 200 km
            ->assertJsonStructure(['taux_utilisation_30j', 'taux_disponibilite_30j']);
    }

    // ---------- Pages Filament (smoke) ----------

    public function test_every_filament_page_renders_for_the_national_admin(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        $v = $this->vehicle(['immatriculation' => 'SMK1', 'km_actuel' => 100, 'km_vidange' => 5000, 'consommation_theorique' => 10]);
        $driver = Driver::create(['district_id' => $this->d1->id, 'matricule' => 'S1', 'nom_complet' => 'S']);
        $circuit = Circuit::create(['district_id' => $this->d1->id, 'nom' => 'C', 'frequence' => 'hebdomadaire']);
        $circuit->espc()->attach(Espc::create(['district_id' => $this->d1->id, 'nom' => 'E'])->id, ['ordre' => 1]);
        $sortie = $this->sortie($v, ['driver_id' => $driver->id, 'circuit_id' => $circuit->id]);
        $this->refuel($v, 10, ['sortie_id' => $sortie->id]);
        Vidange::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date' => '2026-09-01', 'km' => 100, 'prochain_km' => 5100, 'type' => 'simple']);
        $immo = Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date_debut' => '2026-09-10', 'motif' => 'panne']);
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 700, 'date_effet' => '2026-01-01']);
        $plan = \App\Models\Chronogramme::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'date_prevue' => '2026-09-16']);
        $espc = $circuit->espc()->first();
        $plan->espc()->attach($espc->id, ['ordre' => 1]);
        $personnel = \App\Models\Personnel::create(['district_id' => $this->d1->id, 'matricule' => 'P1', 'nom_complet' => 'Agent']);
        $facture = \App\Models\Facture::create(['district_id' => $this->d1->id, 'numero' => 'F1', 'categorie' => 'carburant', 'fournisseur' => 'X', 'montant' => 1000, 'date_facture' => '2026-09-01']);

        $t = "/admin/{$this->d1->id}";
        $pages = [
            '', '/vehicles', '/vehicles/create', "/vehicles/{$v->id}", "/vehicles/{$v->id}/edit",
            '/drivers', '/drivers/create', "/drivers/{$driver->id}", "/drivers/{$driver->id}/edit",
            '/circuits', '/circuits/create', "/circuits/{$circuit->id}", "/circuits/{$circuit->id}/edit",
            '/espcs', '/espcs/create', "/espcs/{$espc->id}", "/espcs/{$espc->id}/edit", '/livraisons',
            '/personnels', '/personnels/create', "/personnels/{$personnel->id}", "/personnels/{$personnel->id}/edit",
            '/indicateurs', '/finance', '/budgets', '/factures', '/factures/create', "/factures/{$facture->id}", '/reports', '/report-schedules', '/import-classeur', '/chronogrammes', '/chronogrammes/create', "/chronogrammes/{$plan->id}/edit",
            '/sorties/sortie-vehicules', '/sorties/sortie-vehicules/create', "/sorties/sortie-vehicules/{$sortie->id}/edit",
            '/ravitaillements', '/ravitaillements/create', '/vidanges', '/vidanges/create',
            '/immobilisations', '/immobilisations/create', "/immobilisations/{$immo->id}/edit",
            '/carburant', '/analyse-consommation', '/calendrier-maintenance', '/fuel-prices', '/seuils-alerte', '/expenses', '/roles', '/users',
        ];
        foreach ($pages as $path) {
            $response = $this->get($t.$path);
            $this->assertSame(200, $response->getStatusCode(), "GET {$t}{$path} → {$response->getStatusCode()} ".($response->headers->get('Location') ?? ''));
        }
    }
}
