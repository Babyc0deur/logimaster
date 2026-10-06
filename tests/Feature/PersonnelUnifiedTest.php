<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Driver;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PlanningValidated;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Une seule liste « Personnel » : chauffeurs, chefs de mission et passagers ; la fiche chauffeur suit la personne. */
class PersonnelUnifiedTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
    }

    private function chauffeur(array $extra = []): Personnel
    {
        return Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KOUAME YAO', 'fonction' => 'chauffeur', 'telephone' => '0700000000'] + $extra);
    }

    public function test_a_driver_entered_in_personnel_gets_a_driver_record_and_mobile_access(): void
    {
        $p = $this->chauffeur(['categorie_permis' => 'C', 'permis_expiration' => '2027-03-01']);

        $d = $p->fresh()->driver;
        $this->assertNotNull($d);
        $this->assertSame('KOUAME YAO', $d->nom_complet);
        $this->assertSame('C', $d->categorie_permis);
        $this->assertSame('2027-03-01', $d->permis_expiration->toDateString());
        $this->assertMatchesRegularExpression('/^CH-[A-Z0-9]{6}$/', $d->matricule);
        $this->assertSame($d->matricule, $p->fresh()->matricule);
        $this->assertNotNull($p->fresh()->user);   // convoyeur d'office

        $p->update(['nom_complet' => 'KOUAME YAO ALAIN', 'categorie_permis' => 'D']);
        $this->assertSame(['KOUAME YAO ALAIN', 'D'], [$d->fresh()->nom_complet, $d->fresh()->categorie_permis]);
        $this->assertSame(1, Driver::count());
    }

    public function test_a_driver_created_elsewhere_import_or_api_appears_in_personnel(): void
    {
        $d = Driver::create(['district_id' => $this->district->id, 'matricule' => 'CH-001', 'nom_complet' => 'BAMBA SEKOU', 'categorie_permis' => 'B']);

        $p = Personnel::where('driver_id', $d->id)->first();
        $this->assertNotNull($p);
        $this->assertSame(['chauffeur', 'CH-001', 'B'], [$p->fonction, $p->matricule, $p->categorie_permis]);
        $this->assertNotNull($p->user);
        $this->assertSame(1, Personnel::count());

        $d->update(['nom_complet' => 'BAMBA SEKOU II']);
        $this->assertSame('BAMBA SEKOU II', $p->fresh()->nom_complet);
        $this->assertSame(1, Personnel::count());
    }

    public function test_an_existing_chief_with_the_same_name_is_linked_not_duplicated_and_stays_active(): void
    {
        $chef = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'Diallo  Moussa', 'fonction' => 'chef_mission']);
        $d = Driver::create(['district_id' => $this->district->id, 'matricule' => 'CH-9', 'nom_complet' => 'DIALLO MOUSSA']);

        $this->assertSame($d->id, $chef->fresh()->driver_id);
        $this->assertSame('chef_mission', $chef->fresh()->fonction);
        $this->assertSame(1, Personnel::count());

        $chef->fresh()->update(['telephone' => '0101']);
        $this->assertSame('actif', $d->fresh()->statut);   // chef de mission qui conduit : la fiche chauffeur n'est pas désactivée
    }

    public function test_leaving_the_driver_function_deactivates_the_driver_record(): void
    {
        $p = $this->chauffeur();
        $p->update(['fonction' => 'passager']);

        $this->assertSame('inactif', $p->fresh()->driver->statut);
    }

    public function test_the_driver_sees_the_runs_he_drives_and_is_notified_on_validation(): void
    {
        Notification::fake();
        $p = $this->chauffeur();
        $vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D1']);
        $plan = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $p->fresh()->driver_id,
            'date_prevue' => '2026-10-14', 'validation_statut' => 'soumis']);
        app(ChronogrammeWorkflow::class)->validate($this->district->id, CarbonImmutable::parse('2026-10-01'), User::factory()->create(['is_active' => true]));

        $user = $p->fresh()->user;
        Notification::assertSentTo($user, PlanningValidated::class);

        $user->forceFill(['password' => 'Secret123', 'must_change_password' => false])->save();
        $this->travelTo(CarbonImmutable::parse('2026-10-14 07:00'));
        $token = $this->postJson('/api/mobile/login', ['identifiant' => $p->fresh()->identifiant, 'password' => 'Secret123'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/mobile/sorties', ['Authorization' => "Bearer $token"])->assertOk()->assertJsonPath('data.0.id', $plan->id);
    }

    public function test_users_list_shows_office_accounts_only(): void
    {
        $this->chauffeur();   // crée un compte mobile
        $office = User::factory()->create(['is_active' => true, 'name' => 'Gestionnaire Bureau']);
        $office->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($office, 'web');
        Filament::setTenant($this->district);

        $this->get("/admin/{$this->district->id}/users")->assertOk()->assertSee('Gestionnaire Bureau')->assertDontSee('KOUAME YAO');
        $this->get("/admin/{$this->district->id}/personnels")->assertOk()->assertSee('KOUAME YAO')->assertSee('Chauffeur');
    }
}
