<?php

namespace Tests\Feature;

use App\Domain\Import\Workbook\WorkbookImporter;
use App\Models\District;
use App\Models\Espc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Chargement en masse des classeurs réels : variantes d'onglets, valeurs saisies à la main, association aux districts. */
class DistrictWorkbooksTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'KOUASSI KOUASSIKRO', 'sync_id' => 'K1', 'sync_password_hash' => 'x']);
        District::create(['region_id' => $region->id, 'name' => 'YOPOUGON-EST', 'sync_id' => 'Y1', 'sync_password_hash' => 'x']);
        $this->dir = sys_get_temp_dir().'/lm_'.uniqid();
        mkdir($this->dir.'/OCTOBRE', 0777, true);
        mkdir($this->dir.'/MAI', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir.'/*') ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function workbook(string $path, string $sitesSheet, string $sitesHeader, array $vehicles): void
    {
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName($sitesSheet);
        $w->addRow(Row::fromValues(['', '', '']));
        $w->addRow(Row::fromValues(['N°', $sitesHeader]));
        $w->addRow(Row::fromValues([1, 'CSR A']));
        $w->addRow(Row::fromValues([2, 'CSU B']));
        $w->addNewSheetAndMakeItCurrent()->setName('VEHICULES');
        $w->addRow(Row::fromValues(['']));
        $w->addRow(Row::fromValues(['N°', 'Immatriculation du véhicule', 'District sanitaire', 'Date de réception du véhicule', 'Nom du Bailleur', 'Marque du véhicule', 'Modèle',
            'Vignette', 'Validité assurance', 'Poids du véhicule à vide', 'Type de carburant']));
        foreach ($vehicles as $i => $v) {
            $w->addRow(Row::fromValues([$i + 1, ...$v]));
        }
        $w->close();
    }

    public function test_both_site_sheet_names_and_hand_typed_values_are_accepted(): void
    {
        $path = $this->dir.'/a.xlsx';
        $this->workbook($path, 'Liste des ESPC', 'Nom des lieux de déplacement pour activité CA (ESPC, DISTRICTS)', [
            ['1234 AB 01', 'X', '2021-12-01', 'PNLP', 'TOYOTA', 'HIACE', 2021, '2027-01-01', '2800KG', 'Gasoil'],
            ['5678 CD 01', 'X', 'NA', 'PNLP', 'NISSAN', 'NAVARA', 'NA', 'non disponible', 'NA', 'Gasoil'],
        ]);

        $importer = new WorkbookImporter;
        $report = $importer->import($path, $this->district->id, true, ['Liste des Sites', 'VEHICULES'], true);

        $this->assertTrue($report->committed);
        $this->assertFalse($report->hasErrors(), implode(' | ', $report->errorLines()));
        $this->assertSame(2, Espc::where('district_id', $this->district->id)->count());
        $this->assertSame(2, Vehicle::where('district_id', $this->district->id)->count());
        $this->assertEquals(2800, Vehicle::where('immatriculation', '1234 AB 01')->value('poids_vide') ?? 2800);
    }

    public function test_unreadable_dates_are_dropped_only_in_lenient_mode(): void
    {
        $path = $this->dir.'/b.xlsx';
        $this->workbook($path, 'Liste des Sites', 'Nom des ESPC et autres sites de destination', [
            ['9999 ZZ 01', 'X', '1905-07-13', 'PNLP', 'TOYOTA', 'HIACE', 2021, '2027-01-01', 1500, 'Gasoil'],
        ]);

        $strict = (new WorkbookImporter)->import($path, $this->district->id, false, ['VEHICULES']);
        $this->assertTrue($strict->hasErrors());
        $this->assertSame(0, Vehicle::count());

        $importer = new WorkbookImporter;
        $lenient = $importer->import($path, $this->district->id, false, ['VEHICULES'], true);
        $this->assertFalse($lenient->hasErrors());
        $this->assertSame(1, Vehicle::count());
        $this->assertNotEmpty($importer->warnings);
    }

    public function test_command_matches_files_to_districts_and_keeps_the_latest_month(): void
    {
        $this->workbook($this->dir.'/MAI/LogiMaster Version 30092025 DS KOUASSI_KOUASSIKRO.xlsx', 'Liste des Sites', 'Nom des ESPC et autres sites de destination',
            [['1111 AA 01', 'X', '2021-12-01', 'PNLP', 'TOYOTA', 'HIACE', 2021, '2027-01-01', 1500, 'Gasoil']]);
        $this->workbook($this->dir.'/OCTOBRE/LogiMaster Version 30092025 DS KOUASSI_KOUASSIKRO.xlsx', 'Liste des ESPC', 'Nom des ESPC et autres sites de destination',
            [['2222 BB 01', 'X', '2021-12-01', 'PNLP', 'NISSAN', 'NAVARA', 2021, '2027-01-01', 1500, 'Gasoil']]);
        $this->workbook($this->dir.'/OCTOBRE/LogiMaster Version 30092025 DS YOP EST  .xlsx', 'Liste des ESPC', 'Nom des ESPC et autres sites de destination', []);

        $this->artisan('import:districts', ['folder' => $this->dir])->assertSuccessful();

        $this->assertSame(['2222 BB 01'], Vehicle::where('district_id', $this->district->id)->pluck('immatriculation')->all()); // octobre, pas mai
        $this->assertSame(2, Espc::where('district_id', $this->district->id)->count());
        $yop = District::where('name', 'YOPOUGON-EST')->first();
        $this->assertSame(2, Espc::where('district_id', $yop->id)->count()); // « YOP EST » reconnu

        // relancer ne crée aucun doublon
        $this->artisan('import:districts', ['folder' => $this->dir])->assertSuccessful();
        $this->assertSame(2, Espc::where('district_id', $this->district->id)->count());
        $this->assertSame(1, Vehicle::where('district_id', $this->district->id)->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->workbook($this->dir.'/OCTOBRE/LogiMaster Version 30092025 DS KOUASSI_KOUASSIKRO.xlsx', 'Liste des Sites', 'Nom des ESPC et autres sites de destination', []);
        $this->artisan('import:districts', ['folder' => $this->dir, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Espc::count());
    }
}
