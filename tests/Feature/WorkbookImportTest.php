<?php

namespace Tests\Feature;

use App\Domain\Import\Workbook\WorkbookExporter;
use App\Domain\Import\Workbook\WorkbookImporter;
use App\Domain\Import\Workbook\WorkbookSpec;
use App\Domain\Indicators\IndicatorService;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\Immobilisation;
use App\Models\IndicatorSnapshot;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Database\Seeders\OrganisationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class WorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'ANYAMA', 'sync_id' => 'DS005', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'ABOBO-EST', 'sync_id' => 'DS001', 'sync_password_hash' => 'x']);
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 715, 'date_effet' => '2024-01-01']);
        FuelPrice::create(['type_carburant' => 'essence', 'prix' => 875, 'date_effet' => '2024-01-01']);
    }

    /** Ligne acceptant des Cell déjà construites (dates formatées) et des valeurs brutes. */
    private function row(array $values): Row
    {
        return new Row(array_map(fn ($v) => $v instanceof Cell ? $v : Cell::fromValue($v), $values));
    }

    /** Cellule date Excel authentique (valeur numérique + format de date). */
    private function d(string $ymd): Cell
    {
        return Cell::fromValue(new \DateTimeImmutable($ymd), (new Style)->setFormat('dd/mm/yyyy'));
    }

    /**
     * Classeur à la structure du modèle Logimaster : ligne 1 = titre, ligne 2 = en-têtes, colonne A = index,
     * plus des onglets de calcul qui doivent être ignorés.
     *
     * @param  array<string, array<int, array<int, mixed>>>  $data  onglet => lignes (colonnes dans l'ordre des en-têtes)
     */
    private function workbook(array $data, ?array $only = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wbt').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $first = true;
        foreach (WorkbookSpec::sheets() as $name => $spec) {
            if ($only !== null && ! in_array($name, $only, true)) {
                continue;
            }
            $first ? $w->getCurrentSheet()->setName($name) : $w->addNewSheetAndMakeItCurrent()->setName($name);
            $first = false;
            $w->addRow($this->row(['', "TITRE {$name}"]));
            $w->addRow($this->row(['', ...$spec['headers']]));
            foreach ($data[$name] ?? [] as $i => $row) {
                $w->addRow($this->row([$i + 1, ...$row]));
            }
        }
        $w->addNewSheetAndMakeItCurrent()->setName('CONSOLIDATION');
        $w->addRow(Row::fromValues(['', 'Mois', 'Immatriculation du véhicule', 'Taux']));
        $w->addRow(Row::fromValues([1, 'x', 'D55032', 99]));
        $w->close();

        return $path;
    }

    private function sample(): array
    {
        $motifLivraison = 'Livraison des produits de santé aux ESPC';

        return [
            'Liste des Sites' => [['CSR GBONOU'], ['MATERNITE YAKOUASSIKRO'], ['CSU ISOLE']],
            'VEHICULES' => [
                ['d55032', 'ANYAMA', '15/06/2019', 'UCP FM', 'FORD', 'FOURGON', 2020, $this->d('2019-03-01'), $this->d('2026-12-31'), 'District', 'Gasoil', '10/01/2026', '10/01/2027', 75000, 2100, 'RAS'],
                ['D54401', 'ANYAMA', null, null, 'TOYOTA', 'HILUX', null, '2018', null, 'Mutualisation', 'Essence', null, null, null, null, null],
            ],
            'CHRONOGRAMME' => [
                [$this->d('2026-09-01'), $motifLivraison, 'CIRCUIT 1', 'CSR GBONOU', $this->d('2026-09-07'), null],
                [$this->d('2026-09-01'), $motifLivraison, 'CIRCUIT 1', 'MATERNITE YAKOUASSIKRO', $this->d('2026-09-07'), null],
                [$this->d('2026-09-01'), $motifLivraison, 'CIRCUIT 2', 'CSU ISOLE', '14/09/2026', 'à confirmer'],
            ],
            'CIRCUITS' => [
                ['D55032', $this->d('2026-09-07'), 'ABRAHAM', 'KONAN', 'YAO', 'KOUA', null, 'CIRCUIT 1', 'DS Anyama', 71342, 'DS Anyama', 71742, $motifLivraison, 400, null],
                ['D55032', '10/09/2026', 'ABRAHAM', null, null, null, null, 'CIRCUIT 3', 'DS Anyama', 71742, null, 71800, $motifLivraison, 58, 'déviation'],
                ['D55032', $this->d('2026-09-20'), 'abraham', 'KONAN', null, null, null, null, null, 71800, null, 71850, 'Supervision', 50, null],
            ],
            'CARBURANT' => [
                ['D55032', 'ABRAHAM', $motifLivraison, 'Gasoil', $this->d('2026-09-07'), 71342, 40, 715, 28600, 'F-001', null],
                ['D55032', null, 'Supervision', 'Gasoil', '21/09/2026', 71850, 12, null, null, null, null],
                ['Total', null, null, null, null, null, 52, null, 37180, null, null],
            ],
            'AUTRES FRAIS' => [
                [$this->d('2026-09-07'), 'KONAN', 'D55032', $motifLivraison, 'Collation', 5000, null],
                ['08/09/2026', 'Garage', 'D55032', null, 'Autres dépenses (à préciser)', '12 500', 'lavage'],
            ],
            'VIDANGES' => [
                ['D55032', $this->d('2026-06-01'), 70000, 'V-1', 25000, 75000, 'RAS'],
            ],
            'IMMOBILISATION' => [
                ['D55032', $this->d('2026-08-01'), $this->d('2026-08-10'), 'Réparation', 50000, 'Garage X', $this->d('2026-08-14'), null],
                ['D54401', $this->d('2026-09-01'), null, 'Vidange', null, null, null, 'en cours'],
            ],
        ];
    }

    public function test_imports_the_whole_workbook_following_the_template_structure(): void
    {
        $report = (new WorkbookImporter)->import($this->workbook($this->sample()), $this->d1->id);

        $this->assertSame([], $report->errorLines(), implode(' | ', $report->errorLines()));
        $this->assertTrue($report->committed);
        $this->assertSame([], $report->missing);

        // Véhicules : normalisation et correspondances avec les listes du classeur
        $v = Vehicle::where('immatriculation', 'D55032')->first();
        $this->assertSame($this->d1->id, $v->district_id);
        $this->assertSame('diesel', $v->type_carburant);                       // « Gasoil »
        $this->assertEquals(715, $v->prix_carburant);                          // prix du carburant du paramétrage
        $this->assertSame('2019-06-15', $v->date_reception->toDateString());   // JJ/MM/AAAA en texte
        $this->assertSame(2019, $v->annee_circulation);                        // vraie date Excel → année
        $this->assertSame(2020, $v->vignette_annee);
        $this->assertSame('district', $v->appartenance);
        $this->assertSame('2027-01-10', $v->date_ct->toDateString());          // prochain CT
        $this->assertSame('2026-01-10', $v->date_dernier_ct->toDateString());
        $this->assertSame('mutualisation', Vehicle::where('immatriculation', 'D54401')->value('appartenance'));
        $this->assertSame(2018, Vehicle::where('immatriculation', 'D54401')->value('annee_circulation')); // année seule

        // Sites et circuits (ordre de passage conservé)
        $this->assertSame(3, Espc::where('district_id', $this->d1->id)->count());
        $this->assertSame(['CSR GBONOU', 'MATERNITE YAKOUASSIKRO'], Circuit::where('nom', 'CIRCUIT 1')->first()->espc->pluck('nom')->all());

        // Chronogramme : une sortie planifiée par circuit et par date, avec ses sites
        $this->assertSame(2, Chronogramme::count());
        $plan1 = Chronogramme::where('date_prevue', '2026-09-07')->first();
        $this->assertSame('distribution', $plan1->motif);
        $this->assertSame(['CSR GBONOU', 'MATERNITE YAKOUASSIKRO'], $plan1->espc->pluck('nom')->all());
        $this->assertSame('à confirmer', Chronogramme::where('date_prevue', '2026-09-14')->value('commentaires'));

        // Sorties (onglet CIRCUITS)
        $this->assertSame(3, SortieVehicule::count());
        $s1 = SortieVehicule::where('km_depart', 71342)->first();
        $this->assertSame('terminee', $s1->statut);
        $this->assertSame(400, $s1->distance);
        $this->assertTrue($s1->circuit_respecte);                              // sortie planifiée ce jour-là sur ce circuit
        $this->assertSame('KONAN', $s1->chefMission->nom_complet);
        $this->assertEqualsCanonicalizing(['YAO', 'KOUA'], $s1->passagers->pluck('nom_complet')->all());
        $this->assertFalse(SortieVehicule::where('km_depart', 71742)->first()->circuit_respecte); // livraison hors planning
        $this->assertNull(SortieVehicule::where('km_depart', 71800)->first()->circuit_respecte);  // supervision sans circuit
        $this->assertSame(1, Driver::count());                                 // « ABRAHAM » et « abraham » = même chauffeur
        $this->assertSame(1, Personnel::where('fonction', 'chef_mission')->count());
        $this->assertSame('realisee', $plan1->fresh()->statut);                // planning relié à la sortie
        $this->assertSame($s1->id, $plan1->fresh()->sortie_id);
        $this->assertSame($v->id, $plan1->fresh()->vehicle_id);
        $this->assertSame('planifiee', Chronogramme::where('date_prevue', '2026-09-14')->value('statut'));

        // Carburant : ligne « Total » ignorée, prix par défaut du paramétrage, rattachement à la sortie du jour
        $this->assertSame(2, Ravitaillement::count());
        $r1 = Ravitaillement::where('numero_facture', 'F-001')->first();
        $this->assertSame('2026-09-07', $r1->date_ravitaillement->toDateString());
        $this->assertSame($s1->id, $r1->sortie_id);
        $this->assertSame('distribution', $r1->motif);
        $this->assertEquals(715, Ravitaillement::where('litres', 12)->value('prix_unitaire'));

        // Autres frais, vidanges, immobilisations
        $this->assertSame(['collation', 'autre'], Expense::orderBy('date_depense')->pluck('type')->all());
        $this->assertEquals(12500, Expense::where('type', 'autre')->value('montant'));
        $this->assertSame($s1->id, Expense::where('type', 'collation')->value('sortie_id'));
        $this->assertSame(75000, Vidange::first()->prochain_km);
        $closed = Immobilisation::where('motif', 'reparation')->first();
        $this->assertSame(['terminee', 5], [$closed->statut, $closed->duree_jours]);
        $open = Immobilisation::where('motif', 'vidange')->first();
        $this->assertSame(['en_cours', '2026-09-01'], [$open->statut, $open->date_debut->toDateString()]); // début = mois

        // Kilométrage actuel et échéance de vidange recalculés sans régresser
        $v->refresh();
        $this->assertSame(71850, $v->km_actuel);
        $this->assertSame(75000, $v->km_vidange);
    }

    public function test_reimport_is_idempotent(): void
    {
        $importer = new WorkbookImporter;
        $first = $importer->import($this->workbook($this->sample()), $this->d1->id);
        $counts = fn () => [
            Vehicle::count(), Espc::count(), Circuit::count(), Chronogramme::count(), SortieVehicule::count(), Ravitaillement::count(),
            Expense::count(), Vidange::count(), Immobilisation::count(), Driver::count(), Personnel::count(),
        ];
        $before = $counts();

        $second = $importer->import($this->workbook($this->sample()), $this->d1->id);

        $this->assertSame(0, $second->created());
        $this->assertSame($first->created(), $second->updated());
        $this->assertSame($before, $counts());
    }

    public function test_errors_are_reported_per_sheet_and_line_and_nothing_is_saved(): void
    {
        $data = $this->sample();
        $data['CARBURANT'][1][0] = 'INCONNU1';                                  // véhicule absent de l'onglet VEHICULES
        $data['CIRCUITS'][0][9] = 71000; $data['CIRCUITS'][0][11] = 70000;      // arrivée < départ
        $data['AUTRES FRAIS'][1][0] = '31/02/2026';                            // date impossible

        $report = (new WorkbookImporter)->import($this->workbook($data), $this->d1->id);

        $this->assertFalse($report->committed);
        $this->assertSame(3, $report->errorCount());
        $lines = implode("\n", $report->errorLines());
        $this->assertStringContainsString('CARBURANT, ligne 4 :', $lines);      // ligne 4 = 2e donnée (titre en 1, en-têtes en 2)
        $this->assertStringContainsString('introuvable', $lines);
        $this->assertStringContainsString('CIRCUITS, ligne 3 :', $lines);
        $this->assertStringContainsString("inférieur au kilométrage de départ", $lines);
        $this->assertStringContainsString('AUTRES FRAIS, ligne 4 :', $lines);
        $this->assertSame(0, Vehicle::count());                                 // tout ou rien
        $this->assertSame(0, Espc::count());

        $partial = (new WorkbookImporter)->import($this->workbook($data), $this->d1->id, skipInvalid: true);
        $this->assertTrue($partial->committed);
        $this->assertSame(2, Vehicle::count());
        $this->assertSame(1, Ravitaillement::count());                          // la ligne invalide a été écartée
    }

    public function test_missing_sheets_are_listed_and_unrecognised_headers_rejected(): void
    {
        $report = (new WorkbookImporter)->import($this->workbook($this->sample(), only: ['VEHICULES', 'CARBURANT']), $this->d1->id);
        $this->assertContains('CHRONOGRAMME', $report->missing);
        $this->assertContains('IMMOBILISATION', $report->missing);
        $this->assertSame(2, Vehicle::count());

        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('VEHICULES');
        $w->addRow(Row::fromValues(['foo', 'bar']));
        $w->addRow(Row::fromValues(['1', '2']));
        $w->close();
        $bad = (new WorkbookImporter)->import($path, $this->d1->id);
        $this->assertStringContainsString('en-têtes introuvables', $bad->sheets['VEHICULES']->errors[1][0]);
    }

    public function test_vehicle_declared_for_another_district_is_rejected(): void
    {
        $data = ['VEHICULES' => [['AAA111', 'ABOBO-EST', null, null, null, null, null, null, null, null, null, null, null, null, null, null]]];
        $report = (new WorkbookImporter)->import($this->workbook($data), $this->d1->id);

        $this->assertStringContainsString('ABOBO-EST', $report->errorLines()[0]);
        $this->assertSame(0, Vehicle::count());
    }

    public function test_export_then_restore_gives_back_the_same_data(): void
    {
        (new WorkbookImporter)->import($this->workbook($this->sample()), $this->d1->id);
        $models = [Vehicle::class, Circuit::class, Espc::class, Chronogramme::class, SortieVehicule::class, Ravitaillement::class, Expense::class, Vidange::class, Immobilisation::class, Driver::class, Personnel::class];
        $snapshot = fn () => [
            array_map(fn ($m) => $m::where('district_id', $this->d1->id)->count(), $models),
            Chronogramme::orderBy('date_prevue')->get()->map(fn ($p) => $p->date_prevue->toDateString().':'.$p->statut.':'.$p->espc->pluck('nom')->join('>'))->all(),
            SortieVehicule::orderBy('km_depart')->get()->map(fn ($s) => $s->km_depart.'>'.$s->km_arrivee.':'.var_export($s->circuit_respecte, true).':'.$s->passagers->count())->all(),
            Vehicle::orderBy('immatriculation')->get()->map(fn ($v) => [$v->immatriculation, $v->marque, $v->appartenance, $v->type_carburant, $v->km_actuel, $v->km_vidange, $v->date_ct?->toDateString()])->all(),
        ];
        $before = $snapshot();

        $file = (new WorkbookExporter)->export($this->d1->id);

        // Panne : le district est vidé, puis restauré depuis l'export (même mécanisme que pour une migration).
        Vehicle::withTrashed()->forceDelete();                  // supprime en cascade sorties, ravitaillements, vidanges, immobilisations, frais liés
        Expense::query()->delete();
        Chronogramme::query()->delete();
        Circuit::query()->delete();
        Espc::query()->delete();
        Driver::withTrashed()->forceDelete();
        Personnel::query()->delete();
        $this->assertSame(0, Vehicle::count());

        $report = (new WorkbookImporter)->import($file, $this->d1->id);

        $this->assertSame([], $report->errorLines(), implode(' | ', $report->errorLines()));
        $this->assertTrue($report->committed);
        $this->assertSame($before, $snapshot());
    }

    public function test_exported_workbook_keeps_the_template_layout(): void
    {
        (new WorkbookImporter)->import($this->workbook($this->sample()), $this->d1->id);
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open((new WorkbookExporter)->export($this->d1->id));
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        $this->assertSame(array_keys(WorkbookSpec::sheets()), array_keys($sheets));
        $this->assertSame('', (string) $sheets['VEHICULES'][1][0]);                       // colonne A réservée à l'index
        $this->assertSame('Immatriculation du véhicule', $sheets['VEHICULES'][1][1]);     // ligne 2 = en-têtes
        $this->assertSame(1, $sheets['VEHICULES'][2][0]);                                 // index
        $this->assertCount(2 + 2, $sheets['VEHICULES']);                                  // titre + en-têtes + 2 véhicules
        $this->assertSame('CSR GBONOU', $sheets['CHRONOGRAMME'][2][4]);                   // une ligne par site
        $this->assertSame('MATERNITE YAKOUASSIKRO', $sheets['CHRONOGRAMME'][3][4]);
    }

    public function test_empty_template_is_importable_and_has_all_sheets(): void
    {
        $path = (new WorkbookExporter)->template();
        $report = (new WorkbookImporter)->import($path, $this->d1->id);

        $this->assertSame([], $report->missing);
        $this->assertSame(0, $report->created());
        $this->assertFalse($report->hasErrors());
    }

    public function test_imported_data_feeds_the_indicators_for_the_right_month(): void
    {
        (new WorkbookImporter)->import($this->workbook($this->sample()), $this->d1->id);
        app(IndicatorService::class)->computeForDistrict($this->d1->id, CarbonImmutable::parse('2026-09-01'));
        $s = IndicatorSnapshot::where('district_id', $this->d1->id)->get()->keyBy('indicator_key');

        $this->assertEquals(508, $s['distance_totale']->value);                       // 400 + 58 + 50
        $this->assertEqualsWithDelta(2 / 3 * 100, $s['respect_chronogramme']->value, 0.01); // 2 sites livrés (sortie du 07/09 clôturée) sur 3 planifiés
        $this->assertEquals(40 * 715 + 12 * 715 + 5000 + 12500, $s['cout_global']->value); // carburant + autres frais (vidange de juin et immobilisation d'août hors mois)
        $this->assertEquals(52, $s['carburant_par_motif']->value);
        $this->assertEquals(['distribution' => 40, 'supervision' => 12], $s['carburant_par_motif']->breakdown['par_motif']);
        $this->assertEquals(50, $s['respect_circuits']->value);                         // 2 sorties sur circuit : 1 respectée (planifiée), 1 hors planning
    }

    public function test_api_workbook_import_permissions_and_template(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole(User::ROLE_DISTRICT_MANAGER);
        $manager->districts()->attach($this->d1->id);
        Sanctum::actingAs($manager);

        $upload = fn () => new UploadedFile($this->workbook($this->sample()), 'logimaster.xlsx', null, null, true);

        $this->postJson('/api/import/workbook', ['district_id' => $this->d1->id, 'file' => $upload()])
            ->assertOk()->assertJsonPath('committed', true)->assertJsonPath('error_count', 0)->assertJsonPath('sheets.VEHICULES.created', 2);
        $this->postJson('/api/import/workbook', ['district_id' => $this->d2->id, 'file' => $upload()])->assertForbidden();
        $this->get('/api/import/workbook/template')->assertOk();
        $this->get('/api/import/workbook/export?district_id='.$this->d1->id)->assertOk();

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(User::ROLE_SUPERVISEUR);
        $viewer->districts()->attach($this->d1->id);
        Sanctum::actingAs($viewer);
        $this->postJson('/api/import/workbook', ['district_id' => $this->d1->id, 'file' => $upload()])->assertForbidden();
    }

    public function test_filament_page_imports_an_uploaded_workbook_for_the_current_district(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_DISTRICT_MANAGER);
        $user->districts()->attach($this->d1->id);
        $this->actingAs($user, 'web');
        \Filament\Facades\Filament::setCurrentPanel('admin');
        \Filament\Facades\Filament::setTenant($this->d1);

        $file = UploadedFile::fake()->createWithContent('logimaster.xlsx', file_get_contents($this->workbook($this->sample())));

        \Livewire\Livewire::test(\App\Filament\Pages\WorkbookImportPage::class)
            ->set('data.fichier', [$file])
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.committed', true)
            ->assertSet('result.sheets.1.created', 2);   // onglet VEHICULES : 2 véhicules créés

        $this->assertSame(2, Vehicle::where('district_id', $this->d1->id)->count());
        $this->assertSame(3, SortieVehicule::count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->files('imports')); // fichier temporaire supprimé
    }

    public function test_organisation_seeder_loads_113_districts_in_33_regions(): void
    {
        // Les districts du jeu d'essai (DS005 ANYAMA, DS001 ABOBO-EST) existent déjà : le seeder les réutilise.
        $this->seed(OrganisationSeeder::class);
        $this->assertSame(113, District::count());
        $this->assertSame(34, Region::count());          // 33 régions + la région fictive R1 des fixtures
        $this->assertSame('PRES de San-Pédro', Region::where('name', 'NAWA')->first()->pres->name);   // régions rattachées à leur PRES
        $this->assertSame('ANYAMA', District::where('sync_id', 'DS005')->value('name'));
        $this->assertSame('ABIDJAN 1', Region::find(District::where('name', 'YOPOUGON-OUEST SONGON')->value('region_id'))->name);

        $this->seed(OrganisationSeeder::class);          // idempotent
        $this->assertSame(113, District::count());
    }
}
