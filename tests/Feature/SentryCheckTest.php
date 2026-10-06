<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Support\SentryCheck;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bouton « Tester le suivi des erreurs » : réservé au national, sans effet tant que Sentry n'est pas configuré. */
class SentryCheckTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D', 'sync_id' => 'D', 'sync_password_hash' => 'x']);
    }

    private function as(string $role): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->attach($this->district->id);
        $this->actingAs($user, 'web');
        Filament::setTenant($this->district);
    }

    public function test_menu_item_is_only_shown_to_the_national_admin(): void
    {
        $this->as(User::ROLE_PRES_ADMIN);
        $this->get("/admin/{$this->district->id}")->assertOk()->assertSee('Tester le suivi des erreurs');

        $this->as(User::ROLE_DISTRICT_MANAGER);
        $this->get("/admin/{$this->district->id}")->assertOk()->assertDontSee('Tester le suivi des erreurs');
    }

    public function test_without_dsn_nothing_is_sent_and_the_admin_is_told_what_to_configure(): void
    {
        config(['sentry.dsn' => null]);

        $result = SentryCheck::send();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('SENTRY_LARAVEL_DSN', $result['message']);
    }
}
