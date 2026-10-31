<?php

namespace Tests\Feature;

use App\Domain\Fleet\AlertCenter;
use App\Filament\Resources\Circuits\Pages\CreateCircuit;
use App\Filament\Resources\Circuits\Pages\EditCircuit;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Circuits enrichis : décomposition du temps, version, urgence, « livré via », alerte des 8 heures ; desserte des ESPC. */
class CircuitEnrichisTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(User::ROLE_DISTRICT_MANAGER);
        $user->districts()->attach($this->district->id);
        $this->actingAs($user, 'web');
        Filament::setTenant($this->district);
    }

    private function circuit(array $attrs = []): Circuit
    {
        return Circuit::create($attrs + ['district_id' => $this->district->id, 'nom' => 'C1']);
    }

    public function test_total_time_is_the_sum_of_the_parts(): void
    {
        $c = $this->circuit(['temps_aller_min' => 120, 'temps_retour_min' => 100, 'temps_depot_min' => 60, 'temps_coaching_min' => 30]);

        $this->assertSame(310, $c->temps_estime_min);
        $this->assertFalse($c->depasseDureeMax());
    }

    public function test_new_fields_are_optional(): void
    {
        $c = $this->circuit(['version' => null]);   // aucune des nouvelles informations n'est obligatoire

        $this->assertSame('existant', $c->fresh()->version);
        $this->assertFalse($c->urgence);
        $this->assertNull($c->temps_estime_min);
    }

    public function test_total_time_is_kept_when_no_part_is_given(): void
    {
        $this->assertSame(360, $this->circuit(['temps_estime_min' => 360])->temps_estime_min);
    }

    public function test_circuit_over_eight_hours_raises_an_alert(): void
    {
        $this->circuit(['nom' => 'Court', 'temps_estime_min' => 480]);
        $long = $this->circuit(['nom' => 'Long', 'temps_aller_min' => 250, 'temps_retour_min' => 250]);
        $this->circuit(['nom' => 'Inactif', 'temps_estime_min' => 700, 'statut' => 'inactif']);

        $alerts = AlertCenter::all([$this->district->id])->where('type', 'circuit_duree');

        $this->assertCount(1, $alerts);
        $this->assertSame($long->id, $alerts->first()['circuit_id']);
        $this->assertStringContainsString('8h20', $alerts->first()['message']);
    }

    public function test_version_urgence_and_transit_link_are_saved_from_the_form(): void
    {
        $transit = Espc::create(['district_id' => $this->district->id, 'nom' => 'Transit', 'point_transit' => true]);
        $site = Espc::create(['district_id' => $this->district->id, 'nom' => 'Site']);

        Circuit::creating(fn (Circuit $c) => $c->district_id ??= $this->district->id);
        $form = Livewire::test(CreateCircuit::class)->fillForm([
            'nom' => 'Circuit Est', 'statut' => 'actif', 'saison' => 'seche', 'version' => 'optimise', 'urgence' => true,
            'temps_aller_min' => 90, 'temps_retour_min' => 90,
        ]);
        $form->set('data.etapes', [
            'a' => ['espc_id' => $transit->id, 'distance_km' => 10, 'livre_via_espc_id' => null],
            'b' => ['espc_id' => $site->id, 'distance_km' => 5, 'livre_via_espc_id' => $transit->id],
        ])->call('create')->assertHasNoFormErrors();

        $c = Circuit::where('nom', 'Circuit Est')->firstOrFail();
        $this->assertSame('optimise', $c->version);
        $this->assertTrue($c->urgence);
        $this->assertSame(180, $c->temps_estime_min);
        $this->assertSame($transit->id, $c->espc->firstWhere('id', $site->id)->pivot->livre_via_espc_id);
        $this->assertSame('Transit', $c->etapesDetail()[1]['via']);
    }

    public function test_transit_link_must_point_to_another_site_of_the_circuit(): void
    {
        $outside = Espc::create(['district_id' => $this->district->id, 'nom' => 'Hors circuit', 'point_transit' => true]);
        $site = Espc::create(['district_id' => $this->district->id, 'nom' => 'Site']);
        $c = $this->circuit();

        Livewire::test(EditCircuit::class, ['record' => $c->getKey()])
            ->set('data.etapes', ['a' => ['espc_id' => $site->id, 'distance_km' => 5, 'livre_via_espc_id' => $outside->id]])
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull($c->fresh()->espc->first()->pivot->livre_via_espc_id);
    }

    public function test_espc_delivery_fields_are_stored(): void
    {
        $espc = Espc::create(['district_id' => $this->district->id, 'nom' => 'CSR X']);
        $espc->update([
            'distance_aller_retour_km' => 84.5, 'colis_mois' => 40, 'poids_mois_kg' => 320, 'type_voie' => 'piste_fleuve',
            'etat_voie' => 'moyen', 'etat_voie_pluie' => 'impraticable', 'mois_mauvais' => [6, 7, 8], 'point_transit' => true,
        ]);

        $fresh = $espc->fresh();
        $this->assertSame([6, 7, 8], $fresh->mois_mauvais);
        $this->assertTrue($fresh->point_transit);
        $this->assertSame('impraticable', $fresh->etat_voie_pluie);
    }
}
