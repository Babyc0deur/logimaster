<?php

namespace Tests\Feature;

use App\Filament\Pages\SuiviSaisie;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Suivi de la saisie : districts × mois, vert si au moins une sortie saisie, rouge sinon. */
class SuiviSaisieTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_marks_months_with_vehicle_tracking(): void
    {
        $this->travelTo('2026-03-15');
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $meagui = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $gueyo = District::create(['region_id' => $region->id, 'name' => 'GUEYO', 'sync_id' => 'G', 'sync_password_hash' => 'x']);
        $v = Vehicle::create(['district_id' => $meagui->id, 'immatriculation' => 'D1']);
        foreach (['2026-01-05', '2026-01-20', '2026-03-02'] as $d) {
            SortieVehicule::create(['district_id' => $meagui->id, 'vehicle_id' => $v->id, 'date_sortie' => $d, 'km_depart' => 1000, 'km_arrivee' => 1050, 'motif' => 'livraison_espc', 'statut' => 'validee']);
        }
        SortieVehicule::create(['district_id' => $meagui->id, 'vehicle_id' => $v->id, 'date_sortie' => '2026-02-03', 'km_depart' => 1, 'km_arrivee' => 2, 'motif' => 'autre', 'statut' => 'annulee']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin);
        Filament::setTenant($meagui);

        $page = Livewire::test(SuiviSaisie::class)->set('annee', 2026)->assertOk()->assertSee('MEAGUI')->assertSee('GUEYO');
        $data = $page->instance()->getViewData();
        $rows = collect($data['rows'])->keyBy('name');
        $this->assertSame([1 => 2, 3 => 1], $rows['MEAGUI']['mois']);   // février : sortie annulée, non comptée
        $this->assertSame([], $rows['GUEYO']['mois']);
        $this->assertSame(3, $data['elapsed']);
        $this->assertSame(1, $data['aucun']);

        $page->set('manquants', true)->assertSee('GUEYO')->assertSee('MEAGUI');   // MEAGUI : février manquant
        $page->set('recherche', 'gue')->assertSee('GUEYO')->assertDontSee('MEAGUI</td>', false);
    }
}
