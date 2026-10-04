<?php

namespace Tests\Feature;

use App\Domain\Import\Workbook\WorkbookImporter;
use App\Models\District;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Import de l'activité des classeurs réels : période, plaques incrémentées par Excel, dates saisies en période. */
class ActivityImportTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'SOUBRE', 'sync_id' => 'S1', 'sync_password_hash' => 'x']);
        $this->path = sys_get_temp_dir().'/act_'.uniqid().'.xlsx';

        $w = new Writer;
        $w->openToFile($this->path);
        $w->getCurrentSheet()->setName('VEHICULES');
        $w->addRow(Row::fromValues(['']));
        $w->addRow(Row::fromValues(['N°', 'Immatriculation du véhicule', 'District sanitaire', 'Marque du véhicule', 'Type de carburant']));
        $w->addRow(Row::fromValues([1, 'D55141', '', 'ford', 'Gasoil']));
        $w->addNewSheetAndMakeItCurrent()->setName('CARBURANT');
        $w->addRow(Row::fromValues(['']));
        $w->addRow(Row::fromValues(['N°', 'Immatriculation du vehicule', 'Nom du chauffeur', 'Type de carburant', 'Date de transaction', 'Relevé Kilométrage à la prise du caburant', 'Total litres ravitaillés', 'Prix unitaire']));
        $w->addRow(Row::fromValues([1, 'D55141', 'kone  dramane', 'Gasoil', '2025-06-12', 1000, 40, 715]));
        $w->addRow(Row::fromValues([2, 'D55142', 'kone dramane', 'Gasoil', '2025-07-02', 1100, 30, 715]));       // plaque incrémentée par Excel
        $w->addRow(Row::fromValues([3, 'D55140', 'kone dramane', 'Gasoil', '11/08/2025 AU 23/08/2025', 1200, 20, 715])); // période, plaque décrémentée
        $w->addRow(Row::fromValues([4, 'D55141', 'kone dramane', 'Gasoil', '2025-12-15', 1300, 10, 715]));       // hors période
        $w->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_period_filter_increment_fix_and_date_ranges(): void
    {
        $importer = (new WorkbookImporter)->forPeriod('2025-05-01', '2025-10-31');
        $report = $importer->import($this->path, $this->district->id, true, ['VEHICULES', 'CARBURANT'], true);

        $this->assertTrue($report->committed);
        $this->assertFalse($report->hasErrors(), implode(' | ', $report->errorLines()));
        $this->assertSame(1, Vehicle::count());
        $dates = Ravitaillement::orderBy('date_ravitaillement')->pluck('date_ravitaillement')->map->toDateString()->all();
        $this->assertSame(['2025-06-12', '2025-07-02', '2025-08-11'], $dates); // décembre écarté, début de période retenu
        $this->assertSame(1, Ravitaillement::distinct('vehicle_id')->count('vehicle_id'));
        $this->assertTrue(collect($importer->warnings)->contains(fn ($w) => str_contains($w, 'rattachée à D55141')));
        $this->assertTrue(collect($importer->warnings)->contains(fn ($w) => str_contains($w, 'début de période retenu')));
    }

    public function test_names_are_harmonized_and_strict_mode_still_rejects_unknown_plates(): void
    {
        (new WorkbookImporter)->import($this->path, $this->district->id, true, ['VEHICULES', 'CARBURANT'], true);
        $this->assertSame(['KONE DRAMANE'], \App\Models\Driver::pluck('nom_complet')->all());
        $this->assertSame('FORD', Vehicle::first()->marque);

        $strict = (new WorkbookImporter)->import($this->path, $this->district->id, true, ['CARBURANT']);
        $this->assertTrue($strict->hasErrors()); // D55142 introuvable hors mode tolérant
    }
}
