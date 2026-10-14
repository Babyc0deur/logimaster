<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SpreadsheetRow;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use Tests\TestCase;

/** Correctifs de l'audit OWASP Top 10 2025 (14/10/2026). */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_security_headers_and_hides_php_version(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    public function test_spreadsheet_text_starting_with_equals_is_written_as_text_not_formula(): void
    {
        $cells = SpreadsheetRow::from(['=HYPERLINK("http://exemple.org","clic")', 12, null, 'CSR A'])->getCells();
        $this->assertInstanceOf(StringCell::class, $cells[0]);
        $this->assertNotInstanceOf(FormulaCell::class, $cells[0]);
        $this->assertSame('=HYPERLINK("http://exemple.org","clic")', $cells[0]->getValue());
        $this->assertSame(12, $cells[1]->getValue());
    }

    public function test_map_scripts_escape_user_supplied_names(): void
    {
        foreach (['public/js/lm-cartographie.js', 'public/js/lm-saisie-map.js'] as $file) {
            $js = file_get_contents(base_path($file));
            $this->assertStringContainsString('var esc = function', $js, $file);
            $this->assertDoesNotMatchRegularExpression('/\+ (v|o\.v|s|o|d)\.(immatriculation|modele|circuit|district|region|nom|n|r)\b/', $js, $file);
        }
        $this->assertStringContainsString("integrity = 'sha256-", file_get_contents(resource_path('views/filament/pages/cartographie.blade.php')));
    }

    public function test_password_policy_requires_twelve_characters_with_letters_and_digits(): void
    {
        $rule = ['password' => [Password::defaults()]];
        $this->assertTrue(Validator::make(['password' => 'Court123'], $rule)->fails());
        $this->assertTrue(Validator::make(['password' => 'seulementdeslettres'], $rule)->fails());
        $this->assertFalse(Validator::make(['password' => 'Logimaster2026'], $rule)->fails());
    }

    public function test_failed_logins_are_recorded_for_api_and_mobile(): void
    {
        $this->seed(LogimasterRoleSeeder::class);
        User::factory()->create(['email' => 'agent@exemple.org', 'is_active' => true]);

        $this->postJson('/api/auth/login', ['email' => 'agent@exemple.org', 'password' => 'mauvais'])->assertStatus(422);
        $this->postJson('/api/mobile/login', ['identifiant' => 'inconnu.agent', 'password' => 'mauvais'])->assertStatus(422);

        $logs = AuditLog::where('action', 'connexion refusée')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['api', 'mobile'], $logs->pluck('metadata.canal')->sort()->values()->all());
        $this->assertNotNull($logs->firstWhere('metadata.canal', 'api')->user_id);
    }

    public function test_changes_made_in_the_admin_are_recorded_without_secret_values(): void
    {
        $this->seed(LogimasterRoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $this->actingAs($admin, 'web');
        $target = User::factory()->create(['is_active' => true]);
        $target->update(['password' => 'Logimaster2026', 'name' => 'Nouveau nom']);

        $entry = AuditLog::where('action', 'modification User')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertContains('password (masqué)', $entry->metadata['champs']);
        $this->assertStringNotContainsString('Logimaster2026', json_encode($entry->metadata));
    }

    public function test_app_authentication_secret_is_encrypted_and_hidden(): void
    {
        $user = User::factory()->create();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $user->saveAppAuthenticationRecoveryCodes(['aaaa-bbbb']);

        $raw = \DB::table('users')->where('id', $user->id)->value('app_authentication_secret');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
        $this->assertSame(['aaaa-bbbb'], $user->fresh()->getAppAuthenticationRecoveryCodes());
        $this->assertArrayNotHasKey('app_authentication_secret', $user->fresh()->toArray());
    }

    public function test_the_profile_page_with_two_factor_setup_loads_on_the_multi_district_panel(): void
    {
        $this->seed(LogimasterRoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');

        // la page profil est hors district : les liens du menu utilisateur ne doivent pas provoquer d'erreur de route
        $this->get('/admin/profile')->assertOk()->assertSee('Authentification à deux facteurs', false);
    }
}
