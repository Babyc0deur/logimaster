<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorService;
use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportService;
use App\Domain\Reports\Recommendations;
use App\Mail\ReportMail;
use App\Models\Budget;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\Facture;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\Report;
use App\Models\ReportSchedule;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    private Region $region;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        Storage::fake('local');
        $this->region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $this->region->id, 'name' => 'ANYAMA', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => Region::create(['pres_id' => $this->region->pres_id, 'name' => 'R2'])->id, 'name' => 'KORHOGO', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 07:00'));
        $this->service = new ReportService;
        $this->seedData();
    }

    private function user(string $role, array $districts = [], array $attrs = []): User
    {
        $user = User::factory()->create(['is_active' => true] + $attrs);
        $user->assignRole($role);
        $user->districts()->sync(array_map(fn ($d) => $d->id, $districts));

        return $user;
    }

    /** Septembre 2026 dans ANYAMA : une sortie planifiée de 2 sites (1 livré à l'heure, 1 non livré), carburant, frais, budget, facture. */
    private function seedData(): void
    {
        $v = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'D55032', 'marque' => 'FORD', 'bailleur' => 'UCP FM', 'consommation_theorique' => 10, 'km_actuel' => 5000, 'km_vidange' => 5100]);
        $circuit = Circuit::create(['district_id' => $this->d1->id, 'nom' => 'CIRCUIT 1', 'distance_totale' => 100]);
        $sites = collect(['CSR A', 'CSR B'])->map(fn ($n) => Espc::create(['district_id' => $this->d1->id, 'nom' => $n]));
        $circuit->espc()->attach($sites->mapWithKeys(fn ($s, $i) => [$s->id => ['ordre' => $i + 1]])->all());
        $plan = Chronogramme::create(['district_id' => $this->d1->id, 'circuit_id' => $circuit->id, 'vehicle_id' => $v->id, 'date_prevue' => '2026-09-07']);
        $plan->livraisons[0]->update(['statut' => 'livre', 'date_livraison' => '2026-09-07', 'lieu_livraison' => 'site']);
        $plan->livraisons[1]->update(['statut' => 'non_livre', 'raison_non_livraison' => 'Route impraticable']);
        $sortie = SortieVehicule::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'circuit_id' => $circuit->id, 'km_depart' => 4800, 'km_arrivee' => 4960, 'motif' => 'distribution',
            'statut' => 'terminee', 'date_sortie' => '2026-09-07', 'circuit_respecte' => false, 'commentaires' => 'Déviation']);
        Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'sortie_id' => $sortie->id, 'litres' => 30, 'prix_unitaire' => 715, 'date_ravitaillement' => '2026-09-07']);
        Expense::create(['district_id' => $this->d1->id, 'vehicle_id' => $v->id, 'type' => 'collation', 'montant' => 5000, 'date_depense' => '2026-09-07']);
        Budget::create(['district_id' => $this->d1->id, 'period' => '2026-09-01', 'poste' => 'carburant', 'montant_alloue' => 20000]);
        Facture::create(['district_id' => $this->d1->id, 'numero' => 'F1', 'fournisseur' => 'Total', 'date_facture' => '2026-09-08', 'categorie' => 'carburant', 'montant' => 21450, 'statut' => 'a_valider']);
    }

    private function xlsxSheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($c) => is_scalar($c) ? (string) $c : '', $row->toArray());
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        return $sheets;
    }

    public function test_every_report_type_generates_a_valid_pdf_and_excel_file(): void
    {
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $sept = CarbonImmutable::parse('2026-09-01');

        foreach (array_keys(ReportBuilder::TYPES) as $type) {
            $pdf = $this->service->generate($user, $type, 'pdf', $sept);
            $xlsx = $this->service->generate($user, $type, 'xlsx', $sept);

            $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->fichier_path), "{$type} : PDF");
            $this->assertGreaterThan(1500, Storage::disk('local')->size($pdf->fichier_path), "{$type} : PDF non vide");
            $this->assertStringStartsWith('PK', Storage::disk('local')->get($xlsx->fichier_path), "{$type} : XLSX est une archive zip");
            $this->assertSame('2026-09', $pdf->periode);
            $this->assertStringContainsString(ReportBuilder::TYPES[$type], $pdf->titre);
            $this->assertStringContainsString('ANYAMA', $pdf->titre);
        }
    }

    public function test_ddkm_report_content_summary_details_and_recommendations(): void
    {
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $report = $this->service->generate($user, 'ddkm', 'xlsx', CarbonImmutable::parse('2026-09-01'));
        $sheets = $this->xlsxSheets(Storage::disk('local')->path($report->fichier_path));

        $this->assertArrayHasKey('Synthèse', $sheets);
        $flat = collect($sheets)->flatten()->implode(' | ');
        $this->assertStringContainsString('Rapport mensuel DDKM', $flat);
        foreach (['Distance totale parcourue', 'Taux de respect du chronogramme', "Taux d'immobilisation", 'Coût global', 'Utilisation rationnelle', 'Carburant par motif', 'Taux de respect des circuits', 'ESPC'] as $label) {
            $this->assertStringContainsString($label, $flat, $label);
        }
        $this->assertStringContainsString('Route impraticable', $flat);                  // raison d'un site non livré
        $this->assertStringContainsString('Déviation', $flat);                           // écart de circuit
        $this->assertStringContainsString('160', $flat);                                 // distance de la sortie
        $this->assertStringContainsString('Commentaires et recommandations', $flat);

        // Les snapshots manquants ont été calculés au passage
        $this->assertSame(9, \App\Models\IndicatorSnapshot::where('district_id', $this->d1->id)->count());
    }

    public function test_financial_and_supplier_reports_show_budget_and_bailleur_figures(): void
    {
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $fin = collect($this->xlsxSheets(Storage::disk('local')->path($this->service->generate($user, 'financier', 'xlsx', CarbonImmutable::parse('2026-09-01'))->fichier_path)))->flatten()->implode(' | ');
        $this->assertStringContainsString('Dépassé', $fin);                              // carburant : 21 450 dépensés pour 20 000 alloués
        $this->assertStringContainsString('21 450', $fin);
        $this->assertStringContainsString('À valider', $fin);

        $bail = collect($this->xlsxSheets(Storage::disk('local')->path($this->service->generate($user, 'bailleur', 'xlsx', CarbonImmutable::parse('2026-09-01'))->fichier_path)))->flatten()->implode(' | ');
        $this->assertStringContainsString('UCP FM', $bail);
        $this->assertStringContainsString('D55032', $bail);
    }

    public function test_scope_is_always_limited_to_the_users_districts(): void
    {
        $manager = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);

        // Demander un autre district ne renvoie rien d'autre : refus explicite
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->generate($manager, 'flotte', 'pdf', CarbonImmutable::parse('2026-09-01'), ['district_id' => $this->d2->id]);
    }

    public function test_national_report_covers_every_district_and_region_report_only_its_region(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        $regionManager = $this->user(User::ROLE_REGION_MANAGER, [], ['region_id' => $this->region->id]);

        $all = $this->service->generate($admin, 'flotte', 'pdf', CarbonImmutable::parse('2026-09-01'));
        $this->assertStringContainsString('2 districts', $all->titre);

        $mine = $this->service->generate($regionManager, 'flotte', 'pdf', CarbonImmutable::parse('2026-09-01'));
        $this->assertStringNotContainsString('2 districts', $mine->titre);
        $this->assertStringContainsString('District ANYAMA', $mine->titre);              // un seul district dans sa région
    }

    public function test_recommendations_reflect_indicators_off_target_or_all_good(): void
    {
        app(IndicatorService::class)->computeForDistrict($this->d1->id, CarbonImmutable::parse('2026-09-01'));
        $rows = app(IndicatorService::class)->summaryWithTrend([$this->d1->id], CarbonImmutable::parse('2026-09-01'));
        $text = implode("\n", Recommendations::for($rows));

        $this->assertStringContainsString('Chronogramme', $text);
        $this->assertStringContainsString('Route impraticable', $text);                  // cause principale citée
        $this->assertStringContainsString('Circuits', $text);

        $empty = array_map(fn ($r) => ['value' => 0.0, 'breakdown' => [], 'districts' => 0, 'previous' => null, 'delta' => null, 'delta_pct' => null], $rows);
        $this->assertSame(['Tous les indicateurs suivis sont conformes aux objectifs sur la période. Poursuivre les bonnes pratiques.'], Recommendations::for($empty));
    }

    public function test_email_sends_attachments_to_every_recipient(): void
    {
        Mail::fake();
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $pdf = $this->service->generate($user, 'ddkm', 'pdf', CarbonImmutable::parse('2026-09-01'));
        $xlsx = $this->service->generate($user, 'ddkm', 'xlsx', CarbonImmutable::parse('2026-09-01'));

        $this->service->email([$pdf, $xlsx], ['a@x.test', 'b@x.test']);

        Mail::assertSent(ReportMail::class, 2);
        Mail::assertSent(ReportMail::class, fn (ReportMail $m) => $m->hasTo('a@x.test') && count($m->attachments()) === 2
            && str_contains($m->envelope()->subject, 'Rapport mensuel DDKM'));
        $html = (new ReportMail([$pdf, $xlsx]))->render();
        $this->assertStringContainsString('LOGIMASTER PRO', $html);
        $this->assertStringContainsString('(PDF)', $html);
    }

    public function test_schedules_are_due_on_the_right_day_and_send_once(): void
    {
        Mail::fake();
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        $monthly = ReportSchedule::create(['user_id' => $user->id, 'type' => 'ddkm', 'formats' => ['pdf', 'xlsx'], 'frequence' => 'mensuelle', 'jour' => 1, 'destinataires' => ['boss@x.test']]);
        $weekly = ReportSchedule::create(['user_id' => $user->id, 'type' => 'carburant', 'formats' => ['pdf'], 'frequence' => 'hebdomadaire', 'jour' => 4, 'destinataires' => ['boss@x.test']]);
        $off = ReportSchedule::create(['user_id' => $user->id, 'type' => 'flotte', 'formats' => ['pdf'], 'frequence' => 'mensuelle', 'jour' => 1, 'destinataires' => ['x@x.test'], 'actif' => false]);

        $this->assertTrue($monthly->isDue(CarbonImmutable::parse('2026-10-01')));
        $this->assertFalse($monthly->isDue(CarbonImmutable::parse('2026-10-02')));
        $this->assertTrue($weekly->isDue(CarbonImmutable::parse('2026-10-01')));         // jeudi
        $this->assertFalse($weekly->isDue(CarbonImmutable::parse('2026-10-02')));
        $this->assertFalse($off->isDue(CarbonImmutable::parse('2026-10-01')));

        $this->assertSame(2, $this->service->runSchedules(CarbonImmutable::parse('2026-10-01')));
        Mail::assertSent(ReportMail::class, 2);
        $this->assertSame('2026-09', Report::where('type', 'ddkm')->first()->periode);   // mensuelle : mois écoulé
        $this->assertSame('2026-10', Report::where('type', 'carburant')->first()->periode); // hebdomadaire : mois en cours
        $this->assertSame(3, Report::count());                                           // ddkm pdf + xlsx + carburant pdf

        $this->assertSame(0, $this->service->runSchedules(CarbonImmutable::parse('2026-10-01')), 'pas de doublon le même jour');
        $this->artisan('reports:send')->assertSuccessful();
    }

    public function test_monthly_ddkm_goes_to_supervisors_only_once(): void
    {
        Mail::fake();
        $this->user(User::ROLE_REGION_MANAGER, [], ['region_id' => $this->region->id, 'email' => 'region@x.test']);
        $this->user(User::ROLE_SUPERVISEUR, [$this->d1, $this->d2], ['email' => 'bailleur@x.test']);
        $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1], ['email' => 'district@x.test']);          // pas un superviseur
        $this->user(User::ROLE_SUPERVISEUR, [], ['email' => 'sans-district@x.test']);                    // aucun district : rien à rapporter

        $today = CarbonImmutable::parse('2026-10-01');
        $this->assertSame(2, $this->service->sendMonthlyToSupervisors($today));
        Mail::assertSent(ReportMail::class, fn ($m) => $m->hasTo('region@x.test'));
        Mail::assertSent(ReportMail::class, fn ($m) => $m->hasTo('bailleur@x.test'));
        Mail::assertNotSent(ReportMail::class, fn ($m) => $m->hasTo('district@x.test'));

        $this->assertSame(0, $this->service->sendMonthlyToSupervisors($today), 'relance sans doublon');
        $this->assertSame(2, Report::where('statut', 'envoye_auto')->count());
    }

    public function test_api_generate_download_email_and_schedule(): void
    {
        Mail::fake();
        $manager = $this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/reports/types')->assertOk()->assertJsonCount(6);
        $this->postJson('/api/reports/generate', ['type' => 'ddkm', 'format' => 'pdf'])->assertStatus(422);
        $this->postJson('/api/reports/generate', ['type' => 'inconnu', 'format' => 'pdf', 'period' => '2026-09'])->assertStatus(422);
        $this->postJson('/api/reports/generate', ['type' => 'flotte', 'format' => 'pdf', 'period' => '2026-09', 'scope' => ['district_id' => $this->d2->id]])->assertForbidden();

        $id = $this->postJson('/api/reports/generate', ['type' => 'carburant', 'format' => 'xlsx', 'period' => '2026-09', 'recipients' => ['dg@x.test']])
            ->assertCreated()->assertJsonPath('format', 'xlsx')->assertJsonStructure(['download_url'])->json('id');
        Mail::assertSent(ReportMail::class, fn ($m) => $m->hasTo('dg@x.test'));

        $this->get("/api/reports/{$id}/download")->assertOk()->assertDownload();
        $this->postJson("/api/reports/{$id}/email", ['recipients' => ['pas-un-email']])->assertStatus(422);
        $this->postJson("/api/reports/{$id}/email", ['recipients' => ['a@x.test']])->assertOk()->assertJsonPath('sent_to.0', 'a@x.test');
        $this->getJson('/api/reports')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/reports/schedule', ['type' => 'ddkm', 'formats' => ['pdf'], 'frequence' => 'hebdomadaire', 'jour' => 9, 'destinataires' => ['a@x.test']])->assertStatus(422);
        $sid = $this->postJson('/api/reports/schedule', ['type' => 'ddkm', 'formats' => ['pdf', 'xlsx'], 'frequence' => 'mensuelle', 'jour' => 1, 'destinataires' => ['a@x.test']])->assertCreated()->json('id');
        $this->getJson('/api/report-schedules')->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/report-schedules/{$sid}")->assertNoContent();

        // Les rapports d'un autre utilisateur ne sont pas accessibles
        Sanctum::actingAs($this->user(User::ROLE_DISTRICT_MANAGER, [$this->d1]));
        $this->get("/api/reports/{$id}/download")->assertNotFound();
        // Lecture seule : pas de génération
        Sanctum::actingAs($this->user(User::ROLE_SUPERVISEUR, [$this->d1]));
        $this->getJson('/api/reports/types')->assertOk();
        $this->postJson('/api/reports/generate', ['type' => 'flotte', 'format' => 'pdf', 'period' => '2026-09'])->assertForbidden();
    }

    public function test_report_pages_render(): void
    {
        $admin = $this->user(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        $this->service->generate($admin, 'flotte', 'pdf', CarbonImmutable::parse('2026-09-01'));
        ReportSchedule::create(['user_id' => $admin->id, 'type' => 'ddkm', 'formats' => ['pdf'], 'frequence' => 'mensuelle', 'jour' => 1, 'destinataires' => ['a@x.test']]);

        foreach (['/reports', '/report-schedules'] as $path) {
            $this->assertSame(200, $this->get("/admin/{$this->d1->id}{$path}")->getStatusCode(), $path);
        }
    }
}
