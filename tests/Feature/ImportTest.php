<?php

namespace Tests\Feature;

use App\Domain\Import\Definitions\ChronogrammeImport;
use App\Domain\Import\Definitions\CircuitImport;
use App\Domain\Import\Definitions\DriverImport;
use App\Domain\Import\Definitions\EspcImport;
use App\Domain\Import\Definitions\VehicleImport;
use App\Domain\Import\ImportException;
use App\Domain\Import\SpreadsheetImporter;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    private SpreadsheetImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
        $this->importer = new SpreadsheetImporter;
    }

    /** @param array<int, array<int, mixed>> $rows première ligne = en-têtes */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tst').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('Modèle');
        foreach ($rows as $row) {
            $w->addRow(Row::fromValues($row));
        }
        $w->close();

        return $path;
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tst').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function test_vehicle_import_creates_updates_and_normalizes(): void
    {
        $path = $this->xlsx([
            ['immatriculation', 'marque', 'type_carburant', 'consommation_theorique', 'date_ct', 'statut', 'km_actuel'],
            ['d55032', 'FORD', 'Gasoil', '15,5', '31/03/2027', 'Disponible', 71342],
            ['D54401', 'TOYOTA', 'essence', 11, '2027-01-15', '', 500],
        ]);
        $report = $this->importer->import(new VehicleImport, $path, $this->d1->id);

        $this->assertSame(2, $report->created);
        $v = Vehicle::where('immatriculation', 'D55032')->first();
        $this->assertSame('diesel', $v->type_carburant);           // « Gasoil » → diesel
        $this->assertEquals(15.5, (float) $v->consommation_theorique); // virgule décimale
        $this->assertSame('2027-03-31', $v->date_ct->toDateString()); // JJ/MM/AAAA
        $this->assertSame('disponible', $v->statut);

        // Mise à jour : une cellule vide n'écrase pas la donnée existante
        $path = $this->xlsx([['immatriculation', 'marque', 'km_actuel'], ['D55032', '', 80000]]);
        $report = $this->importer->import(new VehicleImport, $path, $this->d1->id);
        $this->assertSame(1, $report->updated);
        $v->refresh();
        $this->assertSame('FORD', $v->marque);
        $this->assertSame(80000, $v->km_actuel);
        $this->assertSame(2, $v->version);
    }

    public function test_all_or_nothing_reports_line_numbers_then_skip_invalid_imports_good_rows(): void
    {
        $rows = [
            ['immatriculation', 'type_carburant', 'annee_circulation'],
            ['AAA111', 'diesel', 2020],
            ['BBB222', 'kerosene', 2020],     // ligne 3 : carburant inconnu
            ['CCC333', 'diesel', 1950],       // ligne 4 : année invalide
        ];
        $report = $this->importer->import(new VehicleImport, $this->xlsx($rows), $this->d1->id);
        $this->assertSame([3, 4], array_keys($report->errors));
        $this->assertFalse($report->committed);
        $this->assertSame(0, Vehicle::count()); // rien d'importé

        $report = $this->importer->import(new VehicleImport, $this->xlsx($rows), $this->d1->id, skipInvalid: true);
        $this->assertTrue($report->committed);
        $this->assertSame(1, $report->created);
        $this->assertSame(['AAA111'], Vehicle::pluck('immatriculation')->all());
    }

    public function test_cannot_take_over_a_vehicle_of_another_district(): void
    {
        Vehicle::create(['district_id' => $this->d2->id, 'immatriculation' => 'ZZZ999']);
        $report = $this->importer->import(new VehicleImport, $this->xlsx([['immatriculation'], ['ZZZ999']]), $this->d1->id);

        $this->assertStringContainsString('autre district', $report->errors[2][0]);
        $this->assertSame($this->d2->id, Vehicle::first()->district_id);
    }

    public function test_csv_with_semicolon_delimiter_and_headers_by_label(): void
    {
        $path = $this->csv("Matricule;Nom complet;Expiration du permis;Statut\nCH-001;ABRAHAM Kouassi;20/05/2027;Actif\nCH-002;YAO Paul;;suspendu\n");
        $report = $this->importer->import(new DriverImport, $path, $this->d1->id);

        $this->assertSame(2, $report->created);
        $this->assertSame('2027-05-20', Driver::where('matricule', 'CH-001')->first()->permis_expiration->toDateString());
        $this->assertSame('suspendu', Driver::where('matricule', 'CH-002')->first()->statut);
    }

    public function test_circuit_import_creates_missing_espc_in_order(): void
    {
        Espc::create(['district_id' => $this->d1->id, 'nom' => 'CSR GBONOU']);
        $path = $this->xlsx([
            ['nom', 'distance_totale', 'frequence', 'espc'],
            ['CIRCUIT 1', 387, 'Hebdomadaire', 'CSR GBONOU; MATERNITE YAKOUASSIKRO ;csr gbonou'],
        ]);
        $report = $this->importer->import(new CircuitImport, $path, $this->d1->id);

        $this->assertSame(1, $report->created);
        $circuit = Circuit::with('espc')->first();
        $this->assertSame(['CSR GBONOU', 'MATERNITE YAKOUASSIKRO'], $circuit->espc->pluck('nom')->all());
        $this->assertSame(2, Espc::count()); // doublon de casse ignoré, ESPC existant réutilisé
        $this->assertSame('hebdomadaire', $circuit->frequence);
    }

    public function test_circuit_and_espc_import_new_columns_round_trip(): void
    {
        $path = $this->xlsx([
            ['nom', 'adresse', 'email', 'gps_lat', 'gps_lon'],
            ['CSR A', 'Route de A', 'a@exemple.org', 5.4, -4.0],
        ]);
        $this->importer->import(new EspcImport, $path, $this->d1->id);
        $this->assertSame('Route de A', Espc::first()->adresse);
        $this->assertSame('a@exemple.org', Espc::first()->email);

        $path = $this->xlsx([
            ['nom', 'point_depart', 'depart_lat', 'depart_lon', 'espc', 'distances_etapes'],
            ['C1', 'DDKM', 5.5, -4.1, 'CSR A; CSR B', '12; 7,5'],
        ]);
        $report = $this->importer->import(new CircuitImport, $path, $this->d1->id);
        $this->assertSame(1, $report->created);
        $circuit = Circuit::with('espc')->first();
        $this->assertSame('DDKM', $circuit->point_depart);
        $this->assertEquals([12.0, 7.5], $circuit->espc->map(fn ($e) => (float) $e->pivot->distance_km)->all());

        $row = (new CircuitImport)->toRow($circuit);
        $this->assertSame('12; 7.5', $row['distances_etapes']);

        $bad = $this->xlsx([['nom', 'espc', 'distances_etapes'], ['C2', 'CSR A', 'abc']]);
        $this->assertTrue($this->importer->import(new CircuitImport, $bad, $this->d1->id)->hasErrors());
    }

    public function test_chronogramme_import_resolves_references_and_blocks_double_booking(): void
    {
        $vehicle = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'AAA111']);
        Driver::create(['district_id' => $this->d1->id, 'matricule' => 'CH-001', 'nom_complet' => 'X']);
        Circuit::create(['district_id' => $this->d1->id, 'nom' => 'CIRCUIT 1']);

        $path = $this->xlsx([
            ['date_prevue', 'heure_depart', 'vehicule', 'chauffeur', 'circuit', 'motif', 'statut'],
            ['2026-10-12', '07:30', 'AAA111', 'CH-001', 'circuit 1', 'Livraison ESPC', ''],
            ['2026-10-12', '', 'AAA111', '', '', '', ''],                   // ligne 3 : véhicule déjà planifié ce jour
            ['2026-10-13', '', 'INCONNU', '', '', '', ''],                  // ligne 4 : véhicule inconnu
            ['2026-10-14', '', 'AAA111', 'CH-404', '', '', ''],             // ligne 5 : chauffeur inconnu
        ]);
        $report = $this->importer->import(new ChronogrammeImport, $path, $this->d1->id, skipInvalid: true);

        $this->assertSame(1, $report->created);
        $this->assertSame([3, 4, 5], array_keys($report->errors));
        $plan = Chronogramme::first();
        $this->assertSame('distribution', $plan->motif);
        $this->assertSame('planifiee', $plan->statut);
        $this->assertSame('07:30', substr($plan->heure_depart, 0, 5));

        // Réimport de la même ligne = mise à jour, pas de doublon
        $again = $this->importer->import(new ChronogrammeImport, $this->xlsx([
            ['date_prevue', 'vehicule', 'circuit', 'statut'], ['2026-10-12', 'AAA111', 'CIRCUIT 1', 'reportee'],
        ]), $this->d1->id);
        $this->assertSame(1, $again->updated);
        $this->assertSame('reportee', Chronogramme::first()->statut);
        $this->assertSame(1, Chronogramme::count());
    }

    public function test_export_is_reimportable_round_trip(): void
    {
        $circuit = Circuit::create(['district_id' => $this->d1->id, 'nom' => 'CIRCUIT 1', 'distance_totale' => 120, 'frequence' => 'mensuel']);
        $circuit->espc()->attach(Espc::create(['district_id' => $this->d1->id, 'nom' => 'E1'])->id, ['ordre' => 1]);
        Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'AAA111', 'marque' => 'FORD', 'date_ct' => '2027-02-01']);

        foreach ([new CircuitImport, new VehicleImport] as $def) {
            $file = $this->importer->export($def, $this->d1->id);
            $report = $this->importer->import($def, $file, $this->d1->id);
            $this->assertFalse($report->hasErrors(), implode(' | ', $report->errorLines()));
            $this->assertSame(1, $report->updated);
            $this->assertSame(0, $report->created);
        }
        // Et vers un autre district : les données sont créées à l'identique
        $copy = $this->importer->import(new CircuitImport, $this->importer->export(new CircuitImport, $this->d1->id), $this->d2->id);
        $this->assertSame(1, $copy->created);
        $this->assertSame(['E1'], Circuit::where('district_id', $this->d2->id)->first()->espc->pluck('nom')->all());
    }

    public function test_template_has_model_example_and_help_sheets_and_is_importable_when_filled(): void
    {
        $path = $this->importer->template(new VehicleImport);
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($path);
        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }
        $reader->close();
        $this->assertSame(['Modèle', 'Exemple', 'Aide'], $names);

        // Le modèle vide ne contient que les en-têtes : « aucune ligne de données »
        $report = $this->importer->import(new VehicleImport, $path, $this->d1->id);
        $this->assertSame('Le fichier ne contient aucune ligne de données.', $report->errors[1][0]);
    }

    public function test_unrecognized_headers_are_rejected(): void
    {
        $this->expectException(ImportException::class);
        $this->importer->import(new EspcImport, $this->xlsx([['foo', 'bar'], ['1', '2']]), $this->d1->id);
    }

    public function test_api_import_requires_permission_and_district_scope(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole(User::ROLE_DISTRICT_MANAGER);
        $manager->districts()->attach($this->d1->id);
        Sanctum::actingAs($manager);

        $file = fn () => new UploadedFile($this->csv("immatriculation,marque\nAAA111,FORD\n"), 'vehicles.csv', 'text/csv', null, true);

        $this->postJson('/api/import/vehicles', ['district_id' => $this->d1->id, 'file' => $file()])
            ->assertOk()->assertJsonPath('created', 1);
        $this->assertSame('FORD', Vehicle::first()->marque);

        $this->postJson('/api/import/vehicles', ['district_id' => $this->d2->id, 'file' => $file()])->assertForbidden();
        $this->postJson('/api/import/inconnu', ['district_id' => $this->d1->id, 'file' => $file()])->assertNotFound();
        $this->get('/api/import/vehicles/template')->assertOk();
        $this->getJson('/api/import')->assertOk()->assertJsonCount(5);

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(User::ROLE_SUPERVISEUR);
        $viewer->districts()->attach($this->d1->id);
        Sanctum::actingAs($viewer);
        $this->postJson('/api/import/vehicles', ['district_id' => $this->d1->id, 'file' => $file()])->assertForbidden();
    }
}
