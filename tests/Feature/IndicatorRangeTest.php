<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorCatalog;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\IndicatorViewData;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Indicateurs DDKM : 8 cartes dans l'ordre demandé ; jours-véhicule selon la période « Du / Au » ; autres districts calculés malgré le district du menu. */
class IndicatorRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_and_days_follow_the_filter_period(): void
    {
        $this->assertSame(['cout_global', 'utilisation_vehicules', 'taux_immobilisation', 'respect_chronogramme', 'utilisation_rationnelle_carburant',
            'carburant_par_motif', 'respect_circuits', 'distance_totale'], array_keys(IndicatorCatalog::all()));

        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $meagui = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $soubre = District::create(['region_id' => $region->id, 'name' => 'SOUBRE', 'sync_id' => 'S', 'sync_password_hash' => 'x']);
        foreach ([$meagui, $soubre] as $d) {
            $v = Vehicle::create(['district_id' => $d->id, 'immatriculation' => 'V'.$d->name]);
            SortieVehicule::create(['district_id' => $d->id, 'vehicle_id' => $v->id, 'date_sortie' => '2025-10-05', 'km_depart' => 1, 'km_arrivee' => 11, 'motif' => 'autre', 'statut' => 'validee']);
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($user);
        Filament::setTenant($meagui);   // district du menu : ne doit pas masquer SOUBRE

        // 1er au 15 octobre, région entière : 2 véhicules × 15 jours
        $data = IndicatorViewData::get(['region_id' => $region->id, 'date_from' => '2025-10-01', 'date_until' => '2025-10-15']);
        $this->assertSame('periode', $data['mode']);
        $this->assertSame(15, $data['days']);
        $this->assertEquals(30, $data['rows']['utilisation_vehicules']['breakdown']['denominator']);
        $this->assertEquals(2, $data['rows']['utilisation_vehicules']['breakdown']['numerator']);
        $this->assertEquals(20, $data['rows']['distance_totale']['value']);
        $this->assertSame($meagui->id, Filament::getTenant()->getKey());   // district du menu rétabli

        // mois entier : calculs mensuels (31 jours)
        IndicatorViewData::flush();
        $data = IndicatorViewData::get(['region_id' => $region->id, 'date_from' => '2025-10-01', 'date_until' => '2025-10-31']);
        $this->assertSame('mois', $data['mode']);
        $this->assertEquals(62, $data['rows']['utilisation_vehicules']['breakdown']['denominator']);
        $this->assertEquals(20, $data['rows']['distance_totale']['value']);
    }
}
