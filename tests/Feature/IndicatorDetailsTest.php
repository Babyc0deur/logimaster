<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorDetails;
use App\Domain\Indicators\IndicatorService;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Immobilisation;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndicatorDetailsTest extends TestCase
{
    use RefreshDatabase;

    private District $d1;

    private District $d2;

    private CarbonImmutable $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R1']);
        $this->d1 = District::create(['region_id' => $region->id, 'name' => 'D1', 'sync_id' => 'D1', 'sync_password_hash' => 'x']);
        $this->d2 = District::create(['region_id' => $region->id, 'name' => 'D2', 'sync_id' => 'D2', 'sync_password_hash' => 'x']);
        $this->month = CarbonImmutable::parse('2026-09-01');
        $this->travelTo($this->month->addDays(24)); // 25 septembre 2026
    }

    /** Sortie planifiée du 07/09 sur un circuit de 4 sites, véhicule A. */
    private function plan(District $d, string $date = '2026-09-07'): array
    {
        $vehicle = Vehicle::create(['district_id' => $d->id, 'immatriculation' => 'V'.random_int(1000, 9999), 'consommation_theorique' => 10]);
        $circuit = Circuit::create(['district_id' => $d->id, 'nom' => 'CIRCUIT '.random_int(1, 99), 'distance_totale' => 100, 'frequence' => 'hebdomadaire']);
        $sites = collect(['S1', 'S2', 'S3', 'S4'])->map(fn ($n) => Espc::create(['district_id' => $d->id, 'nom' => $n.$d->name]));
        $circuit->espc()->attach($sites->mapWithKeys(fn ($s, $i) => [$s->id => ['ordre' => $i + 1]])->all());
        $plan = Chronogramme::create(['district_id' => $d->id, 'circuit_id' => $circuit->id, 'vehicle_id' => $vehicle->id, 'date_prevue' => $date]);

        return [$plan, $vehicle, $circuit, $sites];
    }

    private function run_(District $d): array
    {
        app(IndicatorService::class)->computeForDistrict($d->id, $this->month);

        return app(IndicatorService::class)->summary([$d->id], $this->month);
    }

    public function test_plan_sites_are_copied_from_the_circuit_and_marked_delivered_when_the_outing_closes(): void
    {
        [$plan, $vehicle] = $this->plan($this->d1);
        $this->assertSame(['S1D1', 'S2D1', 'S3D1', 'S4D1'], $plan->espc->pluck('nom')->all());
        $this->assertSame(['planifie'], $plan->livraisons->pluck('statut')->unique()->all());

        $sortie = $plan->demarrer();
        $this->assertSame(['planifie'], $plan->fresh()->livraisons->pluck('statut')->unique()->all()); // sortie en cours : rien de livré

        $sortie->update(['km_arrivee' => $sortie->km_depart + 100]);                                    // clôture → « terminee »
        $livraisons = $plan->fresh()->livraisons;
        $this->assertSame(['livre'], $livraisons->pluck('statut')->unique()->all());
        $this->assertSame(['site'], $livraisons->pluck('lieu_livraison')->unique()->all());
        $this->assertSame('2026-09-25', $livraisons[0]->date_livraison->toDateString());                  // date de la sortie (aujourd'hui)
    }

    public function test_chronogramme_and_espc_indicators_are_computed_per_site(): void
    {
        [$plan] = $this->plan($this->d1);
        $l = $plan->livraisons;
        // S1 livré à l'heure sur site ; S2 livré avec 1 jour de retard ; S3 livré en transit avec 3 jours de retard ; S4 non livré (raison).
        $l[0]->update(['statut' => 'livre', 'date_livraison' => '2026-09-07', 'lieu_livraison' => 'site']);
        $l[1]->update(['statut' => 'livre', 'date_livraison' => '2026-09-08', 'lieu_livraison' => 'site']);
        $l[2]->update(['statut' => 'livre', 'date_livraison' => '2026-09-10', 'lieu_livraison' => 'transit', 'raison_non_livraison' => 'Route impraticable']);
        $l[3]->update(['statut' => 'non_livre', 'raison_non_livraison' => 'Panne véhicule']);

        $s = $this->run_($this->d1);

        // Chronogramme : seul S1 est livré au plus tard à la date prévue
        $this->assertEqualsWithDelta(25, $s['respect_chronogramme']['value'], 0.001);
        $this->assertSame(1.0, (float) $s['respect_chronogramme']['breakdown']['numerator']);
        $this->assertSame(4.0, (float) $s['respect_chronogramme']['breakdown']['denominator']);
        $missed = collect($s['respect_chronogramme']['breakdown']['non_livres'])->keyBy('site');
        $this->assertSame('en_retard', $missed['S2D1']['statut']);
        $this->assertSame('Panne véhicule', $missed['S4D1']['raison']);

        // Livraison ESPC : conformes = livrées sur site (S1, S2) ; S3 en transit et S4 non livré sont non conformes
        $this->assertEqualsWithDelta(50, $s['respect_espc']['value'], 0.001);
        $this->assertSame(['dans_les_delais' => 1, 'retard_24h' => 1, 'retard_plus_24h' => 1], $s['respect_espc']['breakdown']['delais']);
        $non = collect($s['respect_espc']['breakdown']['non_conformes'])->keyBy('site');
        $this->assertStringContainsString('point de transit', $non['S3D1']['realise']);
        $this->assertSame('Route impraticable', $non['S3D1']['raison']);
        $this->assertSame('non livré', $non['S4D1']['realise']);
    }

    public function test_future_and_cancelled_plans_are_not_counted(): void
    {
        $this->plan($this->d1, '2026-09-28');                    // dans le futur (on est le 25)
        [$cancelled] = $this->plan($this->d1, '2026-09-02');
        $cancelled->update(['statut' => 'annulee', 'raison' => 'Report bailleur']);

        $s = $this->run_($this->d1);

        $this->assertSame('frequence', $s['respect_chronogramme']['breakdown']['source']); // aucun planning échu : repli sur la fréquence des circuits
        $this->assertSame(0.0, (float) $s['respect_chronogramme']['breakdown']['numerator']);
        $this->assertSame([], $s['respect_chronogramme']['breakdown']['non_livres']);
    }

    public function test_utilisation_and_immobilisation_details_per_vehicle(): void
    {
        $a = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'AAA']);
        $b = Vehicle::create(['district_id' => $this->d1->id, 'immatriculation' => 'BBB']);
        foreach (['2026-09-01', '2026-09-02', '2026-09-02'] as $day) {                         // 2 jours distincts pour A
            SortieVehicule::create(['district_id' => $this->d1->id, 'vehicle_id' => $a->id, 'km_depart' => 0, 'km_arrivee' => 10, 'motif' => 'distribution', 'statut' => 'terminee', 'date_sortie' => $day]);
        }
        Immobilisation::create(['district_id' => $this->d1->id, 'vehicle_id' => $b->id, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-10', 'motif' => 'reparation', 'statut' => 'terminee']);

        $s = $this->run_($this->d1);

        $util = $s['utilisation_vehicules']['breakdown']['par_vehicule'];
        $this->assertSame(['jours_utilises' => 2, 'jours_disponibles' => 30], $util['AAA']);
        $this->assertSame(['jours_utilises' => 0, 'jours_disponibles' => 20], $util['BBB']);   // 30 jours − 10 jours d'immobilisation
        $this->assertEqualsWithDelta(2 / 50 * 100, $s['utilisation_vehicules']['value'], 0.001);
        $imm = $s['taux_immobilisation']['breakdown'];
        $this->assertSame(['jours' => 10, 'jours_total' => 30], $imm['par_vehicule']['BBB']);
        $this->assertSame(['reparation' => 10], $imm['jours_par_motif']);
        $this->assertEqualsWithDelta(10 / 60 * 100, $s['taux_immobilisation']['value'], 0.001);
    }

    public function test_aggregation_across_districts_is_weighted_and_details_are_merged(): void
    {
        foreach ([$this->d1, $this->d2] as $d) {
            [$plan] = $this->plan($d);
            $plan->livraisons->each->update(['statut' => 'livre', 'date_livraison' => '2026-09-07', 'lieu_livraison' => 'site']);
        }
        // D2 : un site non livré en plus
        [$plan2] = $this->plan($this->d2, '2026-09-14');
        $plan2->livraisons->each->update(['statut' => 'livre', 'date_livraison' => '2026-09-14', 'lieu_livraison' => 'site']);
        $plan2->livraisons[0]->update(['statut' => 'non_livre', 'date_livraison' => null, 'raison_non_livraison' => 'Grève']);
        foreach ([$this->d1, $this->d2] as $d) {
            app(IndicatorService::class)->computeForDistrict($d->id, $this->month);
        }

        $all = app(IndicatorService::class)->summary(null, $this->month)['respect_chronogramme'];

        $this->assertSame(12.0, (float) $all['breakdown']['denominator']);                  // 4 + 4 + 4
        $this->assertSame(11.0, (float) $all['breakdown']['numerator']);                    // 1 site non livré sur D2
        $this->assertEqualsWithDelta(11 / 12 * 100, $all['value'], 0.001);
        $this->assertSame(2, $all['districts']);
        $this->assertCount(1, $all['breakdown']['non_livres']);                              // listes concaténées
        $this->assertSame('Grève', $all['breakdown']['non_livres'][0]['raison']);
        $this->assertSame(['planifie' => 12, 'livre' => 11], collect($all['breakdown']['par_circuit'])->reduce(
            fn ($c, $row) => ['planifie' => ($c['planifie'] ?? 0) + $row['planifie'], 'livre' => ($c['livre'] ?? 0) + ($row['livre'] ?? 0)], []
        ));
    }

    public function test_trend_compares_with_previous_month_and_catalog_colours_follow_targets(): void
    {
        [$plan] = $this->plan($this->d1, '2026-08-10');
        $plan->livraisons->each->update(['statut' => 'livre', 'date_livraison' => '2026-08-10', 'lieu_livraison' => 'site']);   // août : 100 %
        [$plan2] = $this->plan($this->d1, '2026-09-07');
        $plan2->livraisons[0]->update(['statut' => 'livre', 'date_livraison' => '2026-09-07', 'lieu_livraison' => 'site']);        // sept. : 25 %
        $service = app(IndicatorService::class);
        $service->computeForDistrict($this->d1->id, CarbonImmutable::parse('2026-08-01'));
        $service->computeForDistrict($this->d1->id, $this->month);

        $row = $service->summaryWithTrend([$this->d1->id], $this->month)['respect_chronogramme'];

        $this->assertEqualsWithDelta(25, $row['value'], 0.001);
        $this->assertEqualsWithDelta(100, $row['previous'], 0.001);
        $this->assertEqualsWithDelta(-75, $row['delta'], 0.001);
        $this->assertEqualsWithDelta(-75, $row['delta_pct'], 0.001);

        $this->assertSame('success', IndicatorCatalog::color('respect_chronogramme', 95));      // objectif ≥ 90
        $this->assertSame('warning', IndicatorCatalog::color('respect_chronogramme', 80));
        $this->assertSame('danger', IndicatorCatalog::color('respect_chronogramme', 25));
        $this->assertSame('success', IndicatorCatalog::color('taux_immobilisation', 4));        // objectif ≤ 10
        $this->assertSame('danger', IndicatorCatalog::color('taux_immobilisation', 30));
        $this->assertSame('gray', IndicatorCatalog::color('distance_totale', 1000));            // pas d'objectif
        $this->assertSame('85,5 %', IndicatorCatalog::format('respect_espc', 85.5));
    }

    public function test_every_indicator_has_a_renderable_detail_block(): void
    {
        [$plan, $vehicle] = $this->plan($this->d1);
        $sortie = $plan->demarrer();
        $sortie->update(['km_arrivee' => $sortie->km_depart + 160, 'circuit_respecte' => false, 'commentaires' => 'Déviation route barrée']);
        \App\Models\Ravitaillement::create(['district_id' => $this->d1->id, 'vehicle_id' => $vehicle->id, 'sortie_id' => $sortie->id, 'litres' => 20, 'prix_unitaire' => 715, 'motif' => 'distribution']);
        \App\Models\Expense::create(['district_id' => $this->d1->id, 'vehicle_id' => $vehicle->id, 'type' => 'collation', 'montant' => 5000]);

        $summary = $this->run_($this->d1);

        foreach (IndicatorCatalog::all() as $key => $meta) {
            $block = IndicatorDetails::blocks($key, $summary[$key], $summary['cout_global']);
            $this->assertNotEmpty($block['kpis'], "{$key} : chiffres clés");
            $this->assertNotEmpty($block['tables'], "{$key} : tableaux");
        }
        $circuits = IndicatorDetails::blocks('respect_circuits', $summary['respect_circuits'], $summary['cout_global']);
        $this->assertSame('Déviation route barrée', $circuits['tables'][0]['rows'][0][4]);        // raison de l'écart
        $this->assertSame('+60,0', $circuits['tables'][0]['rows'][0][3]);                          // 160 km réalisés − 100 km prévus
        $this->assertSame('60,0 km', $circuits['kpis'][1]['value']);
        $this->assertNotSame('—', $circuits['kpis'][2]['value']);                                  // coût supplémentaire estimé
    }

    public function test_indicator_pages_render_for_the_national_admin(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        [$plan] = $this->plan($this->d1);
        $this->run_($this->d1);

        $t = "/admin/{$this->d1->id}";
        // tableau de bord unique : flotte + 9 indicateurs DDKM, détail de l'indicateur choisi
        foreach ([$t, "{$t}?filters[indicateur]=respect_espc&filters[periode]=2026-09"] as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), $url);
        }
        $this->get("{$t}?filters[indicateur]=respect_espc&filters[periode]=2026-09")->assertSee('Recalculer les indicateurs du mois');
        // l'ancienne adresse des indicateurs renvoie vers le tableau de bord, filtres conservés
        $this->get("{$t}/indicateurs?filters[indicateur]=respect_espc")->assertRedirectContains('respect_espc');
    }
}
