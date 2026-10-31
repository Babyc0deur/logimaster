<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\TourEntry;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Saisie d'une tournée complète au bureau : compteurs, statut et kilométrage de chaque site, plein et autres frais. */
class TourEntryTest extends TestCase
{
    use RefreshDatabase;

    private District $district;

    private Vehicle $vehicle;

    private Chronogramme $plan;

    private User $user;

    /** @var array<int, string> identifiants des trois sites, dans l'ordre du circuit */
    private array $stops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 18:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $this->district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->vehicle = Vehicle::create(['district_id' => $this->district->id, 'immatriculation' => 'D55031', 'km_actuel' => 50000, 'type_carburant' => 'diesel']);
        FuelPrice::query()->delete();
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 725, 'date_effet' => '2026-10-01']);

        $circuit = Circuit::create(['district_id' => $this->district->id, 'nom' => 'CIRCUIT 5', 'point_depart' => 'DDS MEAGUI']);
        foreach (['CSR A', 'CSU B', 'DR C'] as $i => $nom) {
            $circuit->espc()->attach(Espc::create(['district_id' => $this->district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => 10]);
        }
        $this->plan = Chronogramme::create([
            'district_id' => $this->district->id, 'vehicle_id' => $this->vehicle->id, 'circuit_id' => $circuit->id,
            'date_prevue' => '2026-10-14', 'heure_depart' => '07:30', 'validation_statut' => 'valide',
        ]);
        $this->stops = LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('id')->all();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole(User::ROLE_DISTRICT_MANAGER);
    }

    /** Modifie le planning sans passer par le verrou de validation (état de départ d'un test). */
    private function lock(array $values): void
    {
        Chronogramme::whereKey($this->plan->id)->update($values);
    }

    private function entry(array $data): array
    {
        return app(TourEntry::class)->apply($this->plan->fresh(), $data, $this->user);
    }

    private function sites(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $out[$this->stops[$i]] = $row;
        }

        return $out;
    }

    private function errors(array $data): array
    {
        try {
            $this->entry($data);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Une erreur de validation était attendue.');
    }

    public function test_a_whole_tour_is_entered_in_one_go(): void
    {
        $result = $this->entry([
            'km_depart' => 50000, 'km_arrivee' => 50060,
            'sites' => $this->sites([
                ['statut' => 'livre', 'km' => 50010],
                ['statut' => 'transit', 'km' => 50030, 'raison' => 'remis à la mairie'],
                ['statut' => 'non_livre', 'km' => 50040, 'raison' => 'Route coupée'],
            ]),
            'ravitaillement' => ['litres' => 30, 'km_compteur' => 50045, 'station' => 'Total Méagui', 'numero_facture' => 'F-118'],
            'frais' => [['type' => 'peage', 'montant' => 1500, 'beneficiaire' => 'Péage de Soubré']],
        ]);

        $this->assertSame(['sites' => 3, 'plein' => true, 'frais' => 1, 'terminee' => true], $result);

        $sortie = $this->plan->fresh()->sortie;
        $this->assertSame([50000, 50060, 'terminee'], [$sortie->km_depart, $sortie->km_arrivee, $sortie->statut]);
        $this->assertSame(50060, $this->vehicle->fresh()->km_actuel);

        $rows = LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->get();
        $this->assertSame(['livre', 'livre', 'non_livre'], $rows->pluck('statut')->all());
        $this->assertSame(['site', 'transit', null], $rows->pluck('lieu_livraison')->all());
        $this->assertSame([50010, 50030, 50040], $rows->pluck('km_compteur')->all());
        $this->assertSame('2026-10-14', $rows[0]->date_livraison->toDateString());
        $this->assertSame('Route coupée', $rows[2]->raison_non_livraison);
        $this->assertSame($this->user->id, $rows[0]->saisi_par);

        $fuel = Ravitaillement::sole();
        $this->assertSame([$sortie->id, 725.0, 'F-118'], [$fuel->sortie_id, (float) $fuel->prix_unitaire, $fuel->numero_facture]);   // prix du jour, repris du véhicule
        $this->assertSame($this->user->id, $fuel->saisi_par);
        $expense = Expense::sole();
        $this->assertSame([$sortie->id, 'peage', 1500.0], [$expense->sortie_id, $expense->type, (float) $expense->montant]);
    }

    public function test_an_unfinished_tour_leaves_untreated_sites_and_the_run_open(): void
    {
        $result = $this->entry([
            'km_depart' => 50000,
            'sites' => $this->sites([['statut' => 'livre', 'km' => 50010], ['statut' => 'planifie'], ['statut' => 'planifie']]),
        ]);

        $this->assertSame(['sites' => 1, 'plein' => false, 'frais' => 0, 'terminee' => false], $result);
        $this->assertSame('en_cours', $this->plan->fresh()->sortie->statut);
        $this->assertSame(['livre', 'planifie', 'planifie'], LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('statut')->all());
    }

    public function test_the_entry_can_be_redone_to_correct_it_without_duplicating_the_run_or_the_fill(): void
    {
        $this->entry(['km_depart' => 50000, 'sites' => $this->sites([['statut' => 'livre', 'km' => 50010], ['statut' => 'livre', 'km' => 50020], ['statut' => 'planifie']]),
            'ravitaillement' => ['litres' => 20]]);

        // correction : le 2e site n'a finalement pas été livré ; aucun nouveau plein n'est saisi
        $this->entry(['km_depart' => 50005, 'sites' => $this->sites([['statut' => 'livre', 'km' => 50010], ['statut' => 'non_livre', 'raison' => 'Fermé', 'km' => 50020], ['statut' => 'planifie']])]);

        $this->assertSame(1, SortieVehicule::count());
        $this->assertSame(50005, SortieVehicule::first()->km_depart);
        $this->assertSame(['livre', 'non_livre', 'planifie'], LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('statut')->all());
        $this->assertSame(1, Ravitaillement::count());

        // un site remis « à livrer » perd son relevé
        $this->entry(['km_depart' => 50005, 'sites' => $this->sites([['statut' => 'planifie', 'km' => 50010], ['statut' => 'non_livre', 'raison' => 'Fermé'], ['statut' => 'planifie']])]);
        $this->assertNull(LivraisonEspc::find($this->stops[0])->km_compteur);
    }

    public function test_the_odometer_can_never_go_backwards_and_a_reason_is_required(): void
    {
        $errors = $this->errors(['km_depart' => 50000, 'km_arrivee' => 49990,
            'sites' => $this->sites([['statut' => 'livre', 'km' => 50030], ['statut' => 'livre', 'km' => 50020], ['statut' => 'non_livre', 'raison' => ' ']])]);

        $this->assertArrayHasKey("sites.{$this->stops[1]}.km", $errors);
        $this->assertArrayHasKey("sites.{$this->stops[2]}.raison", $errors);
        $this->assertArrayHasKey('km_arrivee', $errors);
        $this->assertSame(0, SortieVehicule::count());   // rien n'est enregistré sur un refus
        $this->assertSame(3, LivraisonEspc::where('statut', 'planifie')->count());

        $this->assertArrayHasKey("sites.{$this->stops[0]}.km", $this->errors(['km_depart' => 50100, 'sites' => $this->sites([['statut' => 'livre', 'km' => 50000]])]));
    }

    public function test_a_price_different_from_the_vehicle_price_must_be_confirmed(): void
    {
        $fuel = ['litres' => 25, 'prix_unitaire' => 760];
        $this->assertArrayHasKey('ravitaillement.prix_confirme', $this->errors(['km_depart' => 50000, 'ravitaillement' => $fuel]));

        $this->entry(['km_depart' => 50000, 'ravitaillement' => $fuel + ['prix_confirme' => true]]);
        $this->assertSame(760.0, (float) Ravitaillement::sole()->prix_unitaire);
    }

    public function test_fuel_and_expense_lines_are_validated(): void
    {
        $this->assertArrayHasKey('ravitaillement.litres', $this->errors(['km_depart' => 50000, 'ravitaillement' => ['litres' => 0.1, 'station' => 'Total']]));
        $this->assertArrayHasKey('frais.0.type', $this->errors(['km_depart' => 50000, 'frais' => [['type' => 'inconnu', 'montant' => 500]]]));
        $this->assertArrayHasKey('frais.0.montant', $this->errors(['km_depart' => 50000, 'frais' => [['type' => 'peage', 'montant' => 0, 'beneficiaire' => 'x']]]));

        FuelPrice::query()->delete();
        $this->assertArrayHasKey('ravitaillement.prix_unitaire', $this->errors(['km_depart' => 50000, 'ravitaillement' => ['litres' => 20]]));

        // des lignes vides ne créent rien
        $this->entry(['km_depart' => 50000, 'ravitaillement' => ['litres' => ''], 'frais' => [['type' => 'peage', 'montant' => '']]]);
        $this->assertSame([0, 0], [Ravitaillement::count(), Expense::count()]);
    }

    public function test_a_cancelled_run_or_a_run_without_vehicle_is_refused(): void
    {
        $this->lock(['statut' => 'annulee']);
        $this->assertArrayHasKey('plan_id', $this->errors(['km_depart' => 50000]));

        $this->lock(['statut' => 'planifiee', 'vehicle_id' => null]);
        $this->assertArrayHasKey('plan_id', $this->errors(['km_depart' => 50000]));
    }

    public function test_a_future_delivery_date_is_refused_and_the_default_date_is_never_in_the_future(): void
    {
        $this->assertArrayHasKey("sites.{$this->stops[0]}.date", $this->errors(['km_depart' => 50000, 'sites' => $this->sites([['statut' => 'livre', 'date' => '2026-10-20']])]));

        $this->lock(['date_prevue' => '2026-10-20']);   // sortie planifiée plus tard : on saisit une livraison d'aujourd'hui
        $this->entry(['km_depart' => 50000, 'sites' => $this->sites([['statut' => 'livre']])]);
        $this->assertSame('2026-10-14', LivraisonEspc::find($this->stops[0])->date_livraison->toDateString());
    }
}
