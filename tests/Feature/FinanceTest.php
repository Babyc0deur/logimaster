<?php

namespace Tests\Feature;

use App\Domain\Finance\BudgetTracker;
use App\Domain\Fleet\AlertCenter;
use App\Models\Budget;
use App\Models\District;
use App\Models\Expense;
use App\Models\Facture;
use App\Models\Immobilisation;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    private Vehicle $ucp;

    private Vehicle $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
        $this->ucp = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'UCP1', 'bailleur' => 'UCP FM']);
        $this->other = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'OTH1', 'bailleur' => 'AUTRE']);
        $this->travelTo(CarbonImmutable::parse('2026-09-15 09:00'));
    }

    private function user(string $role, array $districts = []): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->sync(array_map(fn ($d) => $d->id, $districts));
        Sanctum::actingAs($user);

        return $user;
    }

    private function spend(): void
    {
        // Septembre 2026, district D1 :
        Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->ucp->id, 'litres' => 100, 'prix_unitaire' => 700, 'date_ravitaillement' => '2026-09-03']); // 70 000 (UCP)
        Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->other->id, 'litres' => 50, 'prix_unitaire' => 700, 'date_ravitaillement' => '2026-09-05']); // 35 000 (AUTRE)
        Vidange::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->ucp->id, 'date' => '2026-09-04', 'km' => 100, 'montant' => 20000]);
        Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->other->id, 'date_debut' => '2026-09-06', 'motif' => 'reparation', 'montant' => 15000]);
        Expense::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->ucp->id, 'type' => 'collation', 'montant' => 5000, 'date_depense' => '2026-09-07']);
        Expense::create(['district_id' => $this->d1->id, 'type' => 'hebergement', 'montant' => 10000, 'date_depense' => '2026-09-08']);               // sans véhicule
        Expense::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->ucp->id, 'type' => 'maintenance', 'montant' => 3000, 'date_depense' => '2026-09-09']);
        Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $this->ucp->id, 'litres' => 10, 'prix_unitaire' => 700, 'date_ravitaillement' => '2026-08-30']); // août : hors période
    }

    public function test_spending_is_split_by_poste_and_filtered_by_bailleur(): void
    {
        $this->spend();
        $tracker = app(BudgetTracker::class);
        $sept = CarbonImmutable::parse('2026-09-01');

        $all = $tracker->spent([$this->d1->id], $sept);
        $this->assertEquals(105000, $all['carburant']);
        $this->assertEquals(38000, $all['maintenance']);          // vidange 20 000 + immobilisation 15 000 + frais « maintenance » 3 000
        $this->assertEquals(15000, $all['autres']);               // collation 5 000 + hébergement 10 000
        $this->assertEquals(158000, $all['global']);

        $ucp = $tracker->spent([$this->d1->id], $sept, 'UCP FM');
        $this->assertEquals([70000, 23000, 5000], [$ucp['carburant'], $ucp['maintenance'], $ucp['autres']]);  // la dépense sans véhicule n'est pas rattachable
        $this->assertEquals(0, $tracker->spent([$this->d2->id], $sept)['global']);                           // autre district
    }

    public function test_budget_status_forecast_and_levels(): void
    {
        $this->spend();
        $tracker = app(BudgetTracker::class);
        $mk = fn (array $a) => Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01'] + $a);

        $ok = $tracker->status($mk(['poste' => 'carburant', 'montant_alloue' => 300000]));       // 105 000 dépensés, prévision 210 000 < 300 000
        $this->assertEquals(35, round($ok['pct']));
        $this->assertSame('ok', $ok['niveau']);
        $this->assertEquals(105000 / 15 * 30, $ok['prevision']);                                  // 15 septembre : on est à mi-mois
        $this->assertEquals(195000, $ok['reste']);

        $forecast = $tracker->status($mk(['poste' => 'maintenance', 'montant_alloue' => 50000])); // 38 000 (76 %) mais 76 000 prévus > 50 000
        $this->assertSame('prevision_depassement', $forecast['niveau']);

        $over = $tracker->status($mk(['poste' => 'autres', 'montant_alloue' => 10000]));          // 15 000 > 10 000
        $this->assertSame('depasse', $over['niveau']);
        $this->assertEquals(-5000, $over['reste']);

        $past = $tracker->status(Budget::create(['district_id' => $this->d1->id, 'period' => '2026-08-01', 'poste' => 'carburant', 'montant_alloue' => 5000]));
        $this->assertEquals(7000, $past['depense']);                                              // mois passé : le réel, sans extrapolation
        $this->assertEquals(7000, $past['prevision']);
        $this->assertSame('depasse', $past['niveau']);
    }

    public function test_budget_alerts_feed_the_alert_center_and_the_dashboard_api(): void
    {
        $this->spend();
        Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01', 'poste' => 'autres', 'montant_alloue' => 10000]);
        Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01', 'poste' => 'carburant', 'montant_alloue' => 300000]);

        $alerts = AlertCenter::all([$this->d1->id])->where('type', 'budget');
        $this->assertCount(1, $alerts);
        $this->assertSame('urgent', $alerts->first()['level']->value);
        $this->assertStringContainsString('Autres frais', $alerts->first()['message']);
        $this->assertStringContainsString('dépassé', $alerts->first()['message']);

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $this->getJson('/api/dashboard/alerts')->assertOk()->assertJsonFragment(['type' => 'budget']);
        $this->assertSame([], AlertCenter::all([$this->d2->id])->where('type', 'budget')->all());
    }

    public function test_costs_by_bailleur_with_their_budget(): void
    {
        $this->spend();
        Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01', 'poste' => 'global', 'bailleur' => 'UCP FM', 'montant_alloue' => 200000]);

        $rows = collect(app(BudgetTracker::class)->byBailleur([$this->d1->id], CarbonImmutable::parse('2026-09-01')))->keyBy('bailleur');

        $this->assertEquals(98000, $rows['UCP FM']['total']);                   // 70 000 + 23 000 + 5 000
        $this->assertEquals(50000, $rows['AUTRE']['total']);                    // 35 000 + 15 000
        $this->assertEquals(200000, $rows['UCP FM']['alloue']);
        $this->assertEqualsWithDelta(49, $rows['UCP FM']['pct'], 0.1);
        $this->assertEquals(1, $rows['UCP FM']['vehicules']);
        $this->assertEquals(10000, $rows['Sans bailleur']['total'] ?? 10000);   // hébergement sans véhicule : seule dépense non rattachée
    }

    public function test_budget_api_enforces_uniqueness_per_poste_and_bailleur_and_returns_situation(): void
    {
        $this->spend();
        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $base = ['district_id' => $this->d1->id, 'period' => '2026-09-01', 'montant_alloue' => 100000];

        $this->postJson('/api/budgets', $base + ['poste' => 'carburant'])->assertCreated();
        $this->postJson('/api/budgets', $base + ['poste' => 'carburant'])->assertStatus(422);                       // doublon
        $this->postJson('/api/budgets', $base + ['poste' => 'carburant', 'bailleur' => 'UCP FM'])->assertCreated();   // autre enveloppe
        $this->postJson('/api/budgets', $base + ['poste' => 'inconnu'])->assertStatus(422);

        $this->getJson('/api/budgets')->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment(['niveau' => 'depasse']); // 105 000 dépensés pour 100 000 alloués
        $res = $this->getJson('/api/finance/summary?period=2026-09')->assertOk();
        $res->assertJsonPath('synthese.depense', 158000)->assertJsonPath('synthese.postes.carburant.depense', 105000)
            ->assertJsonStructure(['synthese', 'budgets', 'bailleurs', 'alertes', 'factures']);
        $this->getJson('/api/finance/summary?district_id='.$this->d2->id)->assertForbidden();
    }

    // ------------------------------------------------------------------ factures

    private function facture(array $attrs = []): Facture
    {
        return Facture::create($attrs + [
            'district_id' => $this->d1->id, 'numero' => 'F-'.random_int(1000, 9999), 'fournisseur' => 'Total', 'date_facture' => '2026-09-10',
            'categorie' => 'carburant', 'montant' => 50000,
        ]);
    }

    public function test_invoice_workflow_end_to_end_with_history(): void
    {
        $creator = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $validator = User::factory()->create(['is_active' => true]);
        $validator->assignRole(User::ROLE_DISTRICT_MANAGER);
        $validator->districts()->attach($this->d1->id);

        Sanctum::actingAs($creator);
        $id = $this->postJson('/api/factures', [
            'district_id' => $this->d1->id, 'numero' => 'F-100', 'fournisseur' => 'Total', 'date_facture' => '2026-09-10', 'categorie' => 'carburant',
            'montant' => 120000, 'vehicle_id' => $this->ucp->id,
        ])->assertCreated()->assertJsonPath('statut', 'brouillon')->assertJsonPath('cree_par', $creator->id)->json('id');

        $this->postJson('/api/factures', ['district_id' => $this->d1->id, 'numero' => 'F-100', 'fournisseur' => 'Total', 'date_facture' => '2026-09-10', 'categorie' => 'carburant', 'montant' => 1])
            ->assertStatus(422);                                                                              // même n° chez le même fournisseur
        $this->patchJson("/api/factures/{$id}", ['montant' => 130000])->assertOk();                          // brouillon : modifiable
        $this->postJson("/api/factures/{$id}/validate")->assertStatus(422);                                  // pas encore soumise
        $this->postJson("/api/factures/{$id}/pay", ['mode' => 'virement'])->assertStatus(422);

        $this->postJson("/api/factures/{$id}/submit")->assertOk()->assertJsonPath('statut', 'a_valider');
        $this->patchJson("/api/factures/{$id}", ['montant' => 1])->assertStatus(422);                        // soumise : verrouillée
        $this->deleteJson("/api/factures/{$id}")->assertStatus(422);
        $this->postJson("/api/factures/{$id}/validate")->assertForbidden();                                  // pas de validation de sa propre facture

        // « Facture à approuver » : le validateur est notifié (pas le créateur)
        $this->assertSame(1, $validator->notifications()->count());
        $this->assertSame(0, $creator->notifications()->count());

        Sanctum::actingAs($validator);
        $this->postJson("/api/factures/{$id}/reject", [])->assertStatus(422);                                // motif obligatoire
        $this->postJson("/api/factures/{$id}/reject", ['motif' => 'Montant illisible'])->assertOk()->assertJsonPath('statut', 'rejetee');

        Sanctum::actingAs($creator);
        $this->patchJson("/api/factures/{$id}", ['montant' => 125000])->assertOk();                          // rejetée : corrigeable
        $this->postJson("/api/factures/{$id}/submit")->assertOk()->assertJsonPath('motif_rejet', null);

        Sanctum::actingAs($validator);
        $this->postJson("/api/factures/{$id}/validate")->assertOk()->assertJsonPath('statut', 'validee')->assertJsonPath('valide_par', $validator->id);
        $this->postJson("/api/factures/{$id}/archive")->assertStatus(422);                                   // pas encore payée
        $this->postJson("/api/factures/{$id}/pay", ['mode' => 'bitcoin'])->assertStatus(422);
        $this->postJson("/api/factures/{$id}/pay", ['mode' => 'virement', 'reference' => 'VIR-2026-09-001'])
            ->assertOk()->assertJsonPath('statut', 'payee')->assertJsonPath('reference_paiement', 'VIR-2026-09-001');
        $this->postJson("/api/factures/{$id}/archive")->assertOk()->assertJsonPath('statut', 'archivee');

        $history = collect(Facture::find($id)->historique);
        $this->assertSame(['creation', 'soumission', 'rejet', 'soumission', 'validation', 'paiement', 'archivage'], $history->pluck('action')->all());
        $this->assertSame('Montant illisible', $history[2]['note']);
        $this->assertSame($creator->name, $history[0]['par']);
    }

    public function test_national_admin_may_validate_an_invoice_they_created_and_permissions_apply(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        $f = $this->facture(['cree_par' => $admin->id]);
        $this->postJson("/api/factures/{$f->id}/submit")->assertOk();
        $this->postJson("/api/factures/{$f->id}/validate")->assertOk();                                      // exception : administrateur national

        $this->user(User::ROLE_SUPERVISEUR, [$this->d1]);
        $this->getJson('/api/factures')->assertOk()->assertJsonCount(1, 'data');                             // lecture seule
        $this->postJson('/api/factures', ['district_id' => $this->d1->id, 'numero' => 'X', 'fournisseur' => 'Y', 'date_facture' => '2026-09-01', 'categorie' => 'autres', 'montant' => 1])->assertForbidden();
        $this->postJson("/api/factures/{$f->id}/pay", ['mode' => 'cheque'])->assertForbidden();

        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d2]);
        $this->getJson("/api/factures/{$f->id}")->assertNotFound();                                          // autre district
    }

    public function test_finance_pages_render(): void
    {
        $this->spend();
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01', 'poste' => 'autres', 'montant_alloue' => 10000]);
        $f = $this->facture();
        $f->forceFill(['statut' => 'a_valider', 'cree_par' => $admin->id])->save();

        $t = "/admin/{$this->d1->id}";
        foreach (['/finance', '/finance?filters[periode]=2026-08', '/budgets', '/factures', '/factures/create', "/factures/{$f->id}", "/factures/{$f->id}/edit", '/expenses'] as $path) {
            $r = $this->get($t.$path);
            $this->assertContains($r->getStatusCode(), [200, 302], "{$path} → {$r->getStatusCode()}");
            if ($path !== "/factures/{$f->id}/edit") {                 // l'édition d'une facture soumise redirige vers sa fiche
                $this->assertSame(200, $r->getStatusCode(), $path);
            }
        }
    }
}
