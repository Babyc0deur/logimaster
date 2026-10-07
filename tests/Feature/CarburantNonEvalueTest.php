<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorService;
use App\Domain\Reports\Recommendations;
use App\Models\District;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Carburant rationnel : « Non évalué » (et non 0 %) quand la consommation théorique des véhicules manque. */
class CarburantNonEvalueTest extends TestCase
{
    use RefreshDatabase;

    public function test_rational_fuel_is_not_evaluated_without_theoretical_consumption(): void
    {
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $d = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $v = Vehicle::create(['district_id' => $d->id, 'immatriculation' => 'D55031']);   // pas de consommation théorique
        SortieVehicule::create(['district_id' => $d->id, 'vehicle_id' => $v->id, 'date_sortie' => '2025-10-10', 'km_depart' => 1000, 'km_arrivee' => 1100, 'motif' => 'livraison_espc', 'statut' => 'validee']);
        Ravitaillement::create(['district_id' => $d->id, 'vehicle_id' => $v->id, 'date_ravitaillement' => '2025-10-10', 'litres' => 20, 'prix_unitaire' => 875]);

        $service = app(IndicatorService::class);
        $month = CarbonImmutable::parse('2025-10-01');
        $service->computeForDistrict($d->id, $month);
        $row = $service->summaryWithTrend([$d->id], $month)['utilisation_rationnelle_carburant'];

        $this->assertFalse($row['evaluated']);
        $this->assertSame('Non évalué', IndicatorCatalog::display('utilisation_rationnelle_carburant', $row));
        $this->assertSame('gray', IndicatorCatalog::rowColor('utilisation_rationnelle_carburant', $row));
        $this->assertNull($row['delta']);
        $this->assertStringContainsString('D55031', IndicatorCatalog::notEvaluatedReason('utilisation_rationnelle_carburant', $row['breakdown']));
        $this->assertNull(collect($service->history([$d->id], 'utilisation_rationnelle_carburant', 1, $month))->first()['value']);
        $this->assertTrue(collect(Recommendations::for($service->summaryWithTrend([$d->id], $month)))->contains(fn ($l) => str_contains($l, 'non évaluée')));

        // consommation renseignée : l'indicateur redevient chiffré (100 km × 10 L/100 km = 10 L théoriques / 20 L pris = 50 %)
        $v->update(['consommation_theorique' => 10]);
        $service->computeForDistrict($d->id, $month);
        $row = $service->summary([$d->id], $month)['utilisation_rationnelle_carburant'];
        $this->assertTrue($row['evaluated']);
        $this->assertSame('50,0 %', IndicatorCatalog::display('utilisation_rationnelle_carburant', $row));
    }
}
