<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Sorties\Pages\ListSorties;
use App\Models\District;
use App\Models\Expense;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserMenuAndFiltersTest extends TestCase
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

    private function as(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $user->districts()->attach($this->district->id);
        $this->actingAs($user, 'web');
        Filament::setTenant($this->district);

        return $user;
    }

    public function test_settings_items_are_in_the_user_menu_not_in_the_sidebar(): void
    {
        $this->as(User::ROLE_PRES_ADMIN);
        $sidebar = collect(Filament::getNavigation())->flatMap(fn ($g) => collect($g->getItems())->map(fn ($i) => $i->getLabel()))->all();
        foreach (['Centres de santé (ESPC)', 'Personnel', 'Districts', 'Régions', 'PRES', 'Rôles', 'Utilisateurs'] as $label) {
            $this->assertNotContains($label, $sidebar);   // tous dans le menu du district (en haut à gauche)
        }
        $page = $this->get("/admin/{$this->district->id}")->assertOk();
        foreach (['Centres de santé', 'Personnel', 'Districts', 'Régions', 'PRES', 'Utilisateurs', 'Rôles'] as $label) {
            $page->assertSee(e($label), false);
        }
        $page->assertDontSee('Centres de santé (ESPC)')->assertDontSee('Personnel (chauffeurs');
        foreach (['espcs', 'personnels', 'districts', 'regions', 'pres', 'users', 'roles'] as $slug) {
            $page->assertSee("/admin/{$this->district->id}/{$slug}", false);
            $this->get("/admin/{$this->district->id}/{$slug}")->assertOk();
        }
        $this->get("/admin/{$this->district->id}/regions")->assertSee('Régions')->assertSee('R');
    }

    public function test_list_period_filters_default_to_the_configured_period(): void
    {
        config(['logimaster.default_period' => ['from' => '2025-05-01', 'until' => '2025-10-31']]);
        $this->as(User::ROLE_PRES_ADMIN);
        Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 1000, 'date_depense' => '2025-08-10']);
        Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 2000, 'date_depense' => '2026-02-10']);

        Livewire::test(ListExpenses::class)
            ->assertSet('tableFilters.periode.du', '2025-05-01')
            ->assertSet('tableFilters.periode.au', '2025-10-31')
            ->assertCountTableRecords(1);
        Livewire::test(ListSorties::class)->assertSet('tableFilters.periode.du', '2025-05-01');
    }

    public function test_without_configuration_lists_are_not_filtered_by_period(): void
    {
        $this->as(User::ROLE_PRES_ADMIN);
        Expense::create(['district_id' => $this->district->id, 'type' => 'peage', 'montant' => 1000, 'date_depense' => '2020-01-10']);
        Livewire::test(ListExpenses::class)->assertCountTableRecords(1);
    }
}
