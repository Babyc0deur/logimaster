<?php

namespace Tests\Feature;

use App\Filament\Resources\Chronogrammes\Pages\CreateChronogramme;
use App\Filament\Resources\Personnels\Pages\ListPersonnels;
use App\Models\Chronogramme;
use App\Models\District;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Page installable (PWA), QR code d'installation et gestion des accès mobiles côté bureau. */
class MobileAdminTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
    }

    private function manager(string $role = User::ROLE_DISTRICT_MANAGER): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role);
        $u->districts()->attach($this->district->id);
        $this->actingAs($u, 'web');
        Filament::setTenant($this->district);

        return $u;
    }

    public function test_the_app_page_manifest_and_service_worker_are_served(): void
    {
        $this->get('/m')->assertOk()->assertSee('rel="manifest"', false)->assertSee('/m/manifest.webmanifest', false)->assertSee('LogiMaster Convoyeur');

        $manifest = $this->get('/m/manifest.webmanifest')->assertOk()->json();
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/m', $manifest['scope']);
        $this->assertCount(3, $manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim(strtok($icon['src'], '?'), '/')));   // adresse versionnée (?v=…)
        }

        $sw = $this->get('/m/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/m');
        $this->assertStringContainsString('addEventListener(\'push\'', $sw->getContent());
        $this->assertStringContainsString('notificationclick', $sw->getContent());
        $this->assertStringContainsString('/api/', $sw->getContent());   // l'API n'est jamais mise en cache
    }

    public function test_install_page_shows_the_qr_code_and_warns_when_not_https(): void
    {
        config(['logimaster.mobile_url' => 'http://192.168.1.20:8085/m']);
        $this->get('/m/installer')->assertOk()->assertSee('/m/qr.svg', false)->assertSee('http://192.168.1.20:8085/m')->assertSee('LOGIMASTER_MOBILE_URL');

        config(['logimaster.mobile_url' => 'https://logimaster.exemple.ci/m']);
        $this->get('/m/installer')->assertOk()->assertSee('https://logimaster.exemple.ci/m')->assertDontSee('LOGIMASTER_MOBILE_URL');
    }

    public function test_qr_code_is_an_svg_of_the_app_url(): void
    {
        config(['logimaster.mobile_url' => 'https://logimaster.exemple.ci/m']);
        $r = $this->get('/m/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringStartsWith('<?xml', $r->getContent());
        $this->assertStringContainsString('<svg', $r->getContent());
        // l'adresse change : le QR change
        config(['logimaster.mobile_url' => 'https://autre.exemple.ci/m']);
        $this->assertNotSame($r->getContent(), $this->get('/m/qr.svg')->getContent());
    }

    private function person(string $name, string $fonction = 'chef_mission', array $extra = []): Personnel
    {
        return Personnel::create(['district_id' => $this->district->id, 'nom_complet' => $name, 'fonction' => $fonction] + $extra);
    }

    public function test_every_chef_de_mission_and_passenger_gets_an_access_automatically(): void
    {
        $chef = $this->person('KONÉ Ibrahim');
        $passager = $this->person('YAO Marie', 'passager');
        $autre = $this->person('Visiteur', 'autre');

        $user = $chef->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('kone.ibrahim', $chef->fresh()->identifiant);                 // sans accent ni espace
        $this->assertTrue($user->hasRole(User::ROLE_CONVOYEUR));
        $this->assertTrue($user->isConvoyeurOnly());
        $this->assertTrue($user->must_change_password);
        $this->assertSame([$this->district->id], $user->districts->pluck('id')->all());
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $chef->fresh()->code_acces);
        $this->assertNotNull($passager->fresh()->user);
        $this->assertNull($autre->fresh()->user);                                      // une autre fonction n'est pas convoyeur
        $this->assertNull($autre->fresh()->identifiant);

        // un homonyme reçoit un identifiant distinct
        $this->assertSame('kone.ibrahim.2', $this->person('Koné Ibrahim')->fresh()->identifiant);

        // connexion à l'application avec l'identifiant et le code, sans aucune création manuelle
        $code = $chef->fresh()->code_acces;
        $this->postJson('/api/mobile/login', ['identifiant' => 'Kone.Ibrahim', 'password' => $code])->assertOk()->assertJsonPath('must_change_password', true)->assertJsonPath('user.identifiant', 'kone.ibrahim');
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => 'faux'])->assertStatus(422);
    }

    public function test_the_access_follows_the_file(): void
    {
        $chef = $this->person('KONE IBRAHIM');
        $user = $chef->user;
        $code = $chef->fresh()->code_acces;
        $token = $user->createToken('tel', ['mobile']);

        $chef->update(['statut' => 'inactif']);                                         // quitte le district : accès suspendu
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => $code])->assertStatus(422);

        $chef->update(['statut' => 'actif']);                                           // de retour : réactivé, même identifiant
        $this->assertTrue($user->fresh()->is_active);
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => $code])->assertOk();

        $chef->update(['fonction' => 'autre']);
        $this->assertFalse($user->fresh()->is_active);
        $chef->update(['fonction' => 'passager']);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame(1, User::where('personnel_id', $chef->id)->count());          // jamais de second compte
    }

    public function test_the_temporary_code_disappears_once_the_password_is_chosen(): void
    {
        $chef = $this->person('KONE IBRAHIM');
        $code = $chef->fresh()->code_acces;
        $token = $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => $code])->json('token');

        $this->postJson('/api/mobile/password', ['current_password' => $code, 'password' => 'Nouveau2026', 'password_confirmation' => 'Nouveau2026'], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertNull($chef->fresh()->code_acces);
        $this->assertFalse($chef->fresh()->user->must_change_password);
        $this->assertSame('kone.ibrahim', $chef->fresh()->identifiant);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => 'Nouveau2026'])->assertOk()->assertJsonPath('must_change_password', false);
    }

    public function test_office_can_reset_a_lost_code_and_read_the_credentials(): void
    {
        $this->manager();
        $chef = $this->person('KONE IBRAHIM');
        $user = $chef->user;
        $old = $chef->fresh()->code_acces;
        $user->createToken('tel', ['mobile']);

        Livewire::test(ListPersonnels::class)->callTableAction('reinitialiser_code', $chef)->assertHasNoTableActionErrors();

        $new = $chef->fresh()->code_acces;
        $this->assertNotSame($old, $new);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertSame(0, $user->tokens()->count());                                 // anciens appareils déconnectés
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => $old])->assertStatus(422);
        $this->postJson('/api/mobile/login', ['identifiant' => 'kone.ibrahim', 'password' => $new])->assertOk();

        // la fiche d'accès montre l'identifiant et le code tant que le convoyeur n'a pas choisi son mot de passe
        $html = view('filament.modals.mobile-credentials', ['personnel' => $chef->fresh('user')])->render();
        $this->assertStringContainsString('kone.ibrahim', $html);
        $this->assertStringContainsString($new, $html);
        $this->assertStringContainsString('Code à remettre', str_replace('&#039;', "'", $html) ?: 'Code à remettre');
    }

    public function test_roles_without_permission_cannot_see_or_reset_access(): void
    {
        $chef = $this->person('KONE IBRAHIM');
        $this->manager(User::ROLE_SUPERVISEUR);
        Livewire::test(ListPersonnels::class)->assertTableActionHidden('acces_mobile', $chef)->assertTableActionHidden('reinitialiser_code', $chef);
    }

    public function test_sync_command_gives_an_access_to_existing_personnel(): void
    {
        config(['logimaster.mobile.auto_access' => false]);
        $chef = $this->person('KONE IBRAHIM');
        $passager = $this->person('YAO MARIE', 'passager');
        $this->person('INACTIF', 'passager', ['statut' => 'inactif']);
        $this->person('VISITEUR', 'autre');
        $this->assertSame(0, User::where('personnel_id', '!=', null)->count());          // import ou saisie avant l'application

        $this->artisan('mobile:sync-access', ['--dry-run' => true])->expectsOutputToContain('2 personne(s)')->assertSuccessful();
        $this->assertNull($chef->fresh()->user);

        $this->artisan('mobile:sync-access')->assertSuccessful();
        $this->assertNotNull($chef->fresh()->user);
        $this->assertNotNull($passager->fresh()->user);
        $this->assertSame(2, User::whereNotNull('personnel_id')->count());

        $this->artisan('mobile:sync-access')->expectsOutputToContain('0 personne(s)')->assertSuccessful();   // rejouable
    }

    public function test_automatic_access_can_be_switched_off(): void
    {
        config(['logimaster.mobile.auto_access' => false]);
        $this->assertNull($this->person('KONE IBRAHIM')->fresh()->user);
    }

    public function test_qr_action_is_available_on_the_personnel_list(): void
    {
        $this->manager();
        Livewire::test(ListPersonnels::class)->assertActionVisible('qr_installation');
        $html = view('filament.modals.mobile-qr', ['url' => 'https://logimaster.exemple.ci/m', 'svg' => \App\Http\Controllers\MobileAppController::qrSvg('https://logimaster.exemple.ci/m', 200)])->render();
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('https://logimaster.exemple.ci/m', $html);
        $this->assertStringContainsString('/m/installer', $html);
    }

    public function test_team_field_lists_only_active_personnel_of_the_current_district(): void
    {
        $this->manager();
        $chef = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KONE IBRAHIM', 'fonction' => 'chef_mission']);
        $passager = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'YAO MARIE', 'fonction' => 'passager']);
        Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'INACTIF', 'fonction' => 'passager', 'statut' => 'inactif']);
        Personnel::create(['district_id' => District::create(['region_id' => $this->district->region_id, 'name' => 'AUTRE', 'sync_id' => 'A', 'sync_password_hash' => 'x'])->id, 'nom_complet' => 'HORS DISTRICT', 'fonction' => 'passager']);

        Filament::setCurrentPanel('admin');
        Livewire::test(CreateChronogramme::class)->assertFormFieldExists('personnels', function (\Filament\Forms\Components\Select $field) use ($chef, $passager) {
            $options = $field->getOptions();

            return array_keys($options) == [$chef->id, $passager->id] || (count($options) === 2 && isset($options[$chef->id], $options[$passager->id]));
        });
    }
}
