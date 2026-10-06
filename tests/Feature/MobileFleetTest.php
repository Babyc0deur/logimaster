<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Domain\Fleet\FieldReports;
use App\Domain\Mobile\WebPushSender;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Immobilisation;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\PushSubscription;
use App\Models\Region;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Application convoyeur : planning du district, véhicules, signalements terrain validés au bureau, notification de test. */
class MobileFleetTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private District $district;

    private District $other;

    private Personnel $me;

    private Vehicle $v1;

    private Vehicle $v2;

    private array $h;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00'));   // un mercredi
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'NAWA']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->other = District::create(['region_id' => $region->id, 'name' => 'SOUBRE', 'sync_id' => 'S', 'sync_password_hash' => 'x']);
        $this->v1 = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D1', 'km_actuel' => 42000, 'km_vidange' => 42300, 'date_assurance' => '2026-10-20']);
        $this->v2 = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D2', 'km_actuel' => 10000]);
        Vehicle::create(['district_id' => $this->other->id, 'immatriculation' => 'AUTRE']);

        $this->me = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KONE IBRAHIM', 'fonction' => 'chef_mission']);
        $this->me->fresh()->user->forceFill(['email' => 'ki@exemple.org', 'password' => 'Secret123', 'must_change_password' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->h = ['Authorization' => 'Bearer '.$this->postJson('/api/mobile/login', ['email' => 'ki@exemple.org', 'password' => 'Secret123'])->assertOk()->json('token')];
        $this->app['auth']->forgetGuards();
    }

    private function plan(Vehicle $v, string $date, bool $mine = false, ?District $d = null): Chronogramme
    {
        $p = Chronogramme::create(['district_id' => ($d ?? $this->district)->id, 'vehicle_id' => $v->id, 'date_prevue' => $date, 'heure_depart' => '07:30', 'validation_statut' => 'soumis']);
        $mine && $p->personnels()->attach($this->me->id);

        return $p;
    }

    private function validateMonth(?District $d = null): void
    {
        app(ChronogrammeWorkflow::class)->validate(($d ?? $this->district)->id, CarbonImmutable::parse('2026-10-01'), User::factory()->create(['is_active' => true]));
    }

    public function test_planning_lists_the_validated_runs_of_my_district_for_the_week(): void
    {
        $mine = $this->plan($this->v1, '2026-10-14', true);
        $this->plan($this->v2, '2026-10-16');
        $this->plan($this->v2, '2026-10-25');                                   // semaine suivante
        $draft = Chronogramme::create(['district_id' => $this->district->id, 'vehicle_id' => $this->v1->id, 'date_prevue' => '2026-10-15']);   // brouillon
        $this->validateMonth();
        $this->plan(Vehicle::where('immatriculation', 'AUTRE')->first(), '2026-10-14', false, $this->other);
        $this->validateMonth($this->other);

        $res = $this->getJson('/api/mobile/planning', $this->h)->assertOk()->assertJsonPath('debut', '2026-10-12')->assertJsonPath('fin', '2026-10-18');
        $this->assertSame(['2026-10-14', '2026-10-16'], collect($res->json('data'))->pluck('date')->all());
        $this->assertTrue(collect($res->json('data'))->firstWhere('id', $mine->id)['moi']);
        $this->assertNotContains($draft->id, collect($res->json('data'))->pluck('id'));

        $this->getJson('/api/mobile/planning?debut=2026-10-19&jours=7', $this->h)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_vehicles_show_availability_oil_change_and_deadlines(): void
    {
        $this->plan($this->v1, '2026-10-14');
        $this->validateMonth();
        Immobilisation::create(['district_id' => $this->district->id, 'vehicle_id' => $this->v2->id, 'date_debut' => '2026-10-10', 'motif' => 'panne', 'statut' => 'en_cours']);

        $data = collect($this->getJson('/api/mobile/vehicules', $this->h)->assertOk()->json('data'))->keyBy('immatriculation');
        $this->assertSame(['D1', 'D2'], $data->keys()->all());                      // pas les véhicules d'un autre district
        $this->assertSame('reserve', $data['D1']['disponibilite']);
        $this->assertSame(300, $data['D1']['vidange_reste_km']);
        $this->assertSame(6, $data['D1']['assurance_jours']);
        $this->assertSame('immobilise', $data['D2']['disponibilite']);
        $this->assertSame('Panne mécanique', $data['D2']['immobilisation']['motif']);
    }

    public function test_oil_change_report_is_validated_by_the_office_and_updates_the_vehicle(): void
    {
        $office = User::factory()->create(['is_active' => true]);
        $office->assignRole(User::ROLE_DISTRICT_MANAGER);
        $office->districts()->attach($this->district->id);
        $url = "/api/mobile/vehicules/{$this->v1->id}/signalements";
        $body = ['client_ref' => 'tel-1', 'type' => 'vidange', 'km' => 42310, 'type_vidange' => 'complete', 'montant' => 35000, 'prestataire' => 'Garage Méagui', 'photo' => 'data:image/png;base64,'.self::PNG];
        $this->postJson($url, $body, $this->h)->assertCreated();
        $this->postJson($url, $body, $this->h)->assertOk();                          // envoi rejoué : pas de doublon
        $s = Signalement::sole();
        $this->assertStringStartsWith('signalements/', $s->photo);
        $this->assertSame(1, (int) $this->getJson('/api/mobile/vehicules', $this->h)->json('data.0.signalements_en_attente'));

        $this->assertCount(1, $office->fresh()->notifications);                   // le bureau est prévenu

        $vidange = app(FieldReports::class)->validate($s, $office);
        $this->assertInstanceOf(Vidange::class, $vidange);
        $this->assertSame(['complete', 42310, 47310], [$vidange->type, $vidange->km, $vidange->prochain_km]);
        $this->assertSame([42310, 47310], [(int) $this->v1->fresh()->km_actuel, (int) $this->v1->fresh()->km_vidange]);
        $this->assertSame('valide', $s->fresh()->statut);
        $this->expectException(\RuntimeException::class);
        app(FieldReports::class)->validate($s->fresh(), $office);                    // pas deux fois
    }

    public function test_breakdown_report_makes_the_vehicle_unavailable_once_validated_or_can_be_rejected(): void
    {
        $url = "/api/mobile/vehicules/{$this->v2->id}/signalements";
        $this->postJson($url, ['client_ref' => 'p0', 'type' => 'panne'], $this->h)->assertStatus(422);   // description obligatoire
        $this->postJson($url, ['client_ref' => 'p1', 'type' => 'panne', 'motif' => 'accident', 'description' => 'Choc avant, radiateur percé'], $this->h)->assertCreated();
        $this->postJson($url, ['client_ref' => 'p2', 'type' => 'panne', 'description' => 'Bruit moteur'], $this->h)->assertCreated();
        $office = User::factory()->create(['is_active' => true]);

        app(FieldReports::class)->validate(Signalement::where('client_ref', 'p1')->sole(), $office);
        $this->assertSame('en_maintenance', $this->v2->fresh()->statut);
        $this->assertSame('accident', Immobilisation::sole()->motif);

        app(FieldReports::class)->reject(Signalement::where('client_ref', 'p2')->sole(), $office, 'Doublon');
        $this->assertSame(['rejete', 'Doublon'], [Signalement::where('client_ref', 'p2')->value('statut'), Signalement::where('client_ref', 'p2')->value('commentaire_bureau')]);

        $foreign = Vehicle::where('immatriculation', 'AUTRE')->first();
        $this->postJson("/api/mobile/vehicules/{$foreign->id}/signalements", ['client_ref' => 'x', 'type' => 'vidange'], $this->h)->assertNotFound();
    }

    public function test_office_page_lists_reports_with_a_badge(): void
    {
        $this->postJson("/api/mobile/vehicules/{$this->v1->id}/signalements", ['client_ref' => 'w1', 'type' => 'panne', 'description' => 'Pneu crevé'], $this->h)->assertCreated();
        $office = User::factory()->create(['is_active' => true]);
        $office->assignRole(User::ROLE_DISTRICT_MANAGER);
        $office->districts()->attach($this->district->id);
        $this->app['auth']->forgetGuards();
        $this->actingAs($office, 'web');
        Filament::setTenant($this->district);

        $this->get("/admin/{$this->district->id}/signalements")->assertOk()->assertSee('Pneu crevé')->assertSee('Signalements terrain');
    }

    public function test_push_test_explains_what_blocks_and_sends_when_ready(): void
    {
        $fake = new class extends WebPushSender
        {
            public bool $ready = false;

            public array $sent = [];

            public function configured(): bool
            {
                return $this->ready;
            }

            public function send(PushSubscription $subscription, array $payload): bool
            {
                $this->sent[] = $payload;

                return true;
            }
        };
        $this->app->instance(WebPushSender::class, $fake);

        $this->postJson('/api/mobile/push-test', [], $this->h)->assertOk()->assertJsonPath('ok', false)->assertJsonPath('raison', 'serveur');
        $fake->ready = true;
        $this->postJson('/api/mobile/push-test', [], $this->h)->assertOk()->assertJsonPath('raison', 'appareil');
        $this->postJson('/api/mobile/push-subscriptions', ['endpoint' => 'https://push.exemple.org/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a']], $this->h)->assertNoContent();
        $this->postJson('/api/mobile/push-test', [], $this->h)->assertOk()->assertJsonPath('ok', true)->assertJsonPath('envoyees', 1);
        $this->assertSame('Notification de test', $fake->sent[0]['title']);
    }
}
