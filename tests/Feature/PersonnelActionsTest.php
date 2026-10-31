<?php

namespace Tests\Feature;

use App\Filament\Resources\Personnels\Pages\ListPersonnels;
use App\Models\District;
use App\Models\Personnel;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Liste du personnel : boutons d'action sans libellé (icône + info-bulle) et bouton Supprimer. */
class PersonnelActionsTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
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

    public function test_row_actions_are_icon_buttons_with_tooltips_and_include_delete(): void
    {
        $this->as(User::ROLE_PRES_ADMIN);
        $p = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'KONE IBRAHIM', 'fonction' => 'chef_mission']);

        $html = Livewire::test(ListPersonnels::class)->assertOk()->assertSee('KONE IBRAHIM')->html();
        foreach (['Fiche', 'Modifier', 'Supprimer'] as $tooltip) {
            $this->assertStringContainsString("tooltip", $html);
            $this->assertStringContainsString($tooltip, $html, $tooltip);
        }
        // l'accès mobile ne se gère plus depuis le personnel : l'application mobile est réservée aux comptes du bureau
        foreach (['Accès mobile', 'Réinitialiser le code', 'Identifiant mobile'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        // plus de bouton texte : les libellés ne sont que des info-bulles et des textes d'accessibilité
        $this->assertStringContainsString('fi-icon-btn', $html);
    }

    public function test_deleting_a_person_removes_the_record_and_creating_one_adds_no_account(): void
    {
        $this->as(User::ROLE_PRES_ADMIN);
        $accounts = User::count();
        $p = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'BAMBA AICHA', 'fonction' => 'chef_mission']);
        $this->assertSame($accounts, User::count());   // plus de compte créé avec la fiche

        Livewire::test(ListPersonnels::class)->callTableAction(DeleteAction::class, $p)->assertHasNoTableActionErrors();

        $this->assertNull(Personnel::find($p->id));
        $this->assertSame($accounts, User::count());
    }

    public function test_a_read_only_role_does_not_get_the_delete_button(): void
    {
        $this->as(User::ROLE_REGION_MANAGER);
        $p = Personnel::create(['district_id' => $this->district->id, 'nom_complet' => 'DIALLO SEYDOU', 'fonction' => 'passager']);

        Livewire::test(ListPersonnels::class)->assertTableActionHidden(DeleteAction::class, $p);
    }
}
