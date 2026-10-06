<?php

namespace Tests\Feature;

use App\Domain\Fleet\FleetAnalytics;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Driver;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Analyses du tableau de bord par véhicule et par motif ; un chauffeur peut faire plusieurs circuits dans la journée. */
class FleetAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private District $d;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $this->d = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $admin->districts()->attach($this->d->id);
        $this->actingAs($admin);
        Filament::setTenant($this->d);
    }

    private function sortie(Vehicle $v, string $date, string $motif, int $km): SortieVehicule
    {
        return SortieVehicule::create(['district_id' => $this->d->id, 'vehicle_id' => $v->id, 'date_sortie' => $date, 'km_depart' => 1000, 'km_arrivee' => 1000 + $km, 'motif' => $motif, 'statut' => 'validee']);
    }

    public function test_utilisation_distance_fuel_and_rational_fuel_per_vehicle_and_motif(): void
    {
        $a = Vehicle::create(['district_id' => $this->d->id, 'immatriculation' => 'A1', 'consommation_theorique' => 10]);
        $b = Vehicle::create(['district_id' => $this->d->id, 'immatriculation' => 'B2']);   // sans consommation théorique
        $s1 = $this->sortie($a, '2025-10-01', 'distribution', 100);
        $this->sortie($a, '2025-10-01', 'supervision', 50);   // même jour, autre motif : 1 jour d'utilisation au total
        $this->sortie($a, '2025-10-02', 'distribution', 50);
        $this->sortie($b, '2025-10-03', 'supervision', 30);
        Ravitaillement::create(['district_id' => $this->d->id, 'vehicle_id' => $a->id, 'sortie_id' => $s1->id, 'litres' => 25, 'prix_unitaire' => 700, 'date_ravitaillement' => '2025-10-01']);
        Ravitaillement::create(['district_id' => $this->d->id, 'vehicle_id' => $b->id, 'litres' => 10, 'prix_unitaire' => 700, 'date_ravitaillement' => '2025-10-03', 'motif' => 'supervision']);

        $r = app(FleetAnalytics::class)->compute([$this->d->id], CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2025-10-31'));

        $this->assertSame(['jours' => 2, 'disponibles' => 31, 'taux' => 6.5], $r['utilisation']['A1']);
        $this->assertSame(1, $r['utilisation']['B2']['jours']);
        $this->assertSame(['Livraison ESPC' => 2, 'Supervision' => 1], $r['repartition']['A1']);
        $this->assertSame(['Livraison ESPC' => 150, 'Supervision' => 50], $r['distance']['A1']);
        $this->assertSame(['Livraison ESPC' => 25.0], $r['carburant']['A1']);
        $this->assertSame(['Supervision' => 10.0], $r['carburant']['B2']);
        $this->assertSame(80.0, $r['rationnel']['A1']['taux']);           // 200 km × 10 L/100 = 20 L théoriques / 25 L pris
        $this->assertNull($r['rationnel']['B2']['taux']);                  // pas de consommation théorique : non évalué
        $this->assertSame(['A1', 'B2'], array_keys($r['distance']));       // classé par total
    }

    public function test_dashboard_shows_the_analysis(): void
    {
        $v = Vehicle::create(['district_id' => $this->d->id, 'immatriculation' => 'A1']);
        $this->sortie($v, '2025-10-01', 'distribution', 40);
        $this->get("/admin/{$this->d->id}?filters[district_id]={$this->d->id}&filters[periode]=2025-10")->assertOk()->assertSee('fleet-analysis', false);
    }

    public function test_a_driver_can_do_several_circuits_the_same_day_but_not_at_the_same_time(): void
    {
        $v = Vehicle::create(['district_id' => $this->d->id, 'immatriculation' => 'A1']);
        $driver = Driver::create(['district_id' => $this->d->id, 'matricule' => 'CH1', 'nom_complet' => 'KONE']);
        Chronogramme::create(['district_id' => $this->d->id, 'vehicle_id' => $v->id, 'driver_id' => $driver->id, 'date_prevue' => '2026-10-20', 'heure_depart' => '07:30']);

        // deuxième circuit du même chauffeur dans la journée, l'après-midi : accepté
        $afternoon = Chronogramme::create(['district_id' => $this->d->id, 'vehicle_id' => $v->id, 'driver_id' => $driver->id, 'date_prevue' => '2026-10-20', 'heure_depart' => '13:00']);
        Livewire::test(\App\Filament\Resources\Chronogrammes\Pages\EditChronogramme::class, ['record' => $afternoon->getKey()])
            ->fillForm(['heure_depart' => '14:00'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, Chronogramme::where('driver_id', $driver->id)->whereDate('date_prevue', '2026-10-20')->count());

        // même heure que le premier départ : refusé (chauffeur et véhicule)
        Livewire::test(\App\Filament\Resources\Chronogrammes\Pages\EditChronogramme::class, ['record' => $afternoon->getKey()])
            ->fillForm(['heure_depart' => '07:30'])->call('save')->assertHasFormErrors(['driver_id', 'vehicle_id']);
    }
}
