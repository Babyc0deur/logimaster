<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\IndicatorSnapshot;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\IndicatorViewData;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mois en cours : les indicateurs sont recalculés à l'affichage dès qu'une saisie du district est plus récente que le dernier calcul. */
class LiveIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_month_is_recomputed_only_when_something_changed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00'));
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $d = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin);
        $v = Vehicle::create(['district_id' => $d->id, 'immatriculation' => 'D1', 'km_actuel' => 1000]);
        $s = SortieVehicule::create(['district_id' => $d->id, 'vehicle_id' => $v->id, 'date_sortie' => '2026-10-13', 'km_depart' => 1000, 'km_arrivee' => 1040, 'motif' => 'livraison_espc', 'statut' => 'validee']);
        $filters = ['district_id' => $d->id, 'periode' => '2026-10'];
        $distance = fn () => (float) IndicatorSnapshot::where('district_id', $d->id)->where('indicator_key', 'distance_totale')->value('value');

        IndicatorViewData::get($filters);                                   // premier affichage : calcul
        $this->assertSame(40.0, $distance());
        $first = IndicatorSnapshot::where('district_id', $d->id)->max('computed_at');

        $this->travel(10)->minutes();
        IndicatorViewData::flush();
        IndicatorViewData::get($filters);                                   // rien n'a changé : pas de recalcul
        $this->assertSame($first, IndicatorSnapshot::where('district_id', $d->id)->max('computed_at'));

        $this->travel(5)->minutes();
        $s->update(['km_arrivee' => 1100]);                                // saisie corrigée sur le terrain
        IndicatorViewData::flush();
        $this->assertSame(100.0, (float) collect(IndicatorViewData::get($filters)['rows'])['distance_totale']['value']);
        $this->assertSame(100.0, $distance());
    }

    public function test_past_months_are_not_recomputed_on_display(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00'));
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $d = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin);
        $v = Vehicle::create(['district_id' => $d->id, 'immatriculation' => 'D1']);
        $s = SortieVehicule::create(['district_id' => $d->id, 'vehicle_id' => $v->id, 'date_sortie' => '2026-09-10', 'km_depart' => 0, 'km_arrivee' => 30, 'motif' => 'livraison_espc', 'statut' => 'validee']);
        $filters = ['district_id' => $d->id, 'periode' => '2026-09'];

        IndicatorViewData::get($filters);
        $first = IndicatorSnapshot::where('district_id', $d->id)->max('computed_at');
        $this->travel(10)->minutes();
        $s->update(['km_arrivee' => 90]);
        IndicatorViewData::flush();
        IndicatorViewData::get($filters);
        $this->assertSame($first, IndicatorSnapshot::where('district_id', $d->id)->max('computed_at'));   // mois passé : calcul de nuit
    }
}
