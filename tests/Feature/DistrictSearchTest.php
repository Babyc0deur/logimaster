<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Database\Seeders\OrganisationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sélecteur de district du menu : les districts sont demandés au serveur pendant la saisie, jamais listés d'avance. */
class DistrictSearchTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $districts = []): User
    {
        $this->seed([LogimasterRoleSeeder::class, OrganisationSeeder::class]);
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([$role]);
        $user->districts()->sync(District::whereIn('name', $districts)->pluck('id'));

        return $user;
    }

    public function test_requires_login(): void
    {
        $this->getJson('/ui/districts/search?q=sou')->assertUnauthorized();
    }

    public function test_empty_query_returns_nothing(): void
    {
        $this->actingAs($this->user(User::ROLE_PRES_ADMIN))->getJson('/ui/districts/search')->assertOk()->assertExactJson([]);
    }

    public function test_searches_by_name_with_region_and_limits_results(): void
    {
        $res = $this->actingAs($this->user(User::ROLE_PRES_ADMIN))->getJson('/ui/districts/search?q=soub')->assertOk();
        $row = collect($res->json())->firstWhere('name', 'SOUBRE');
        $this->assertNotNull($row);
        $this->assertSame('NAWA', $row['region']);
        $this->assertStringContainsString('/admin/'.$row['id'], $row['url']);
        $this->assertLessThanOrEqual(15, count($this->getJson('/ui/districts/search?q=a')->json()));
    }

    public function test_only_accessible_districts_are_returned(): void
    {
        $user = $this->user(User::ROLE_DISTRICT_MANAGER, ['ANYAMA']);

        $this->actingAs($user)->getJson('/ui/districts/search?q=soub')->assertOk()->assertExactJson([]);
        $this->actingAs($user)->getJson('/ui/districts/search?q=anya')->assertOk()->assertJsonPath('0.name', 'ANYAMA');
    }
}
