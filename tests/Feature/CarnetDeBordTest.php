<?php

namespace Tests\Feature;

use App\Domain\Chronogramme\TourEntry;
use App\Domain\Fleet\CarnetDeBord;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\Pres;
use App\Models\Region;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\LogimasterRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Heures de la sortie et carnet de bord du véhicule (une ligne par tronçon, de site en site). */
class CarnetDeBordTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private Chronogramme $plan;

    private User $user;

    /** @var array<int, string> */
    private array $stops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LogimasterRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 18:00'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'GBEKE']);
        $district = District::create(['region_id' => $region->id, 'name' => 'MEAGUI', 'sync_id' => 'M', 'sync_password_hash' => 'x']);
        $this->vehicle = Vehicle::create(['district_id' => $district->id, 'immatriculation' => 'D55031', 'km_actuel' => 50000, 'type_carburant' => 'diesel']);
        FuelPrice::query()->delete();
        FuelPrice::create(['type_carburant' => 'diesel', 'prix' => 700, 'date_effet' => '2026-10-01']);

        $circuit = Circuit::create(['district_id' => $district->id, 'nom' => 'CIRCUIT 5']);
        foreach (['CSR A', 'CSU B'] as $i => $nom) {
            $circuit->espc()->attach(Espc::create(['district_id' => $district->id, 'nom' => $nom])->id, ['ordre' => $i + 1, 'distance_km' => 10]);
        }
        $this->plan = Chronogramme::create([
            'district_id' => $district->id, 'vehicle_id' => $this->vehicle->id, 'circuit_id' => $circuit->id,
            'date_prevue' => '2026-10-14', 'heure_depart' => '07:30', 'validation_statut' => 'valide',
        ]);
        $this->stops = LivraisonEspc::where('chronogramme_id', $this->plan->id)->orderBy('ordre')->pluck('id')->all();
        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole(User::ROLE_DISTRICT_MANAGER);
    }

    private function entry(array $data): array
    {
        return app(TourEntry::class)->apply($this->plan->fresh(), $data, $this->user);
    }

    private function fullTour(): void
    {
        $this->entry([
            'km_depart' => 50000, 'km_arrivee' => 50060, 'heure_depart' => '07:30', 'heure_arrivee' => '15:45',
            'sites' => [
                $this->stops[0] => ['statut' => 'livre', 'km' => 50020, 'h_arrivee' => '09:00', 'h_depart' => '09:40'],
                $this->stops[1] => ['statut' => 'livre', 'km' => 50040, 'h_arrivee' => '11:15', 'h_depart' => '12:00'],
            ],
            'ravitaillement' => ['litres' => 20, 'prix_unitaire' => 700, 'km_compteur' => 50030],
        ]);
    }

    public function test_hours_are_saved_and_give_the_duration(): void
    {
        $this->fullTour();
        $sortie = $this->plan->fresh()->sortie;

        $this->assertSame('07:30:00', $sortie->heure_depart);
        $this->assertSame('15:45:00', $sortie->heure_arrivee);
        $this->assertSame(495, $sortie->duree_min);
    }

    public function test_site_hours_are_saved_and_must_follow_each_other(): void
    {
        $this->fullTour();
        $first = LivraisonEspc::find($this->stops[0]);
        $this->assertSame('09:00:00', $first->heure_arrivee);
        $this->assertSame('09:40:00', $first->heure_depart);

        try {
            $this->entry(['km_depart' => 50000, 'heure_depart' => '07:30', 'sites' => [
                $this->stops[0] => ['statut' => 'livre', 'km' => 50020, 'h_arrivee' => '10:00', 'h_depart' => '10:30'],
                $this->stops[1] => ['statut' => 'livre', 'km' => 50040, 'h_arrivee' => '09:00', 'h_depart' => '08:00'],
            ]]);
            $this->fail('Une erreur était attendue.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("sites.{$this->stops[1]}.h_arrivee", $e->errors());
            $this->assertArrayHasKey("sites.{$this->stops[1]}.h_depart", $e->errors());
        }
    }

    public function test_a_tour_over_eight_hours_raises_an_alert(): void
    {
        $this->entry(['km_depart' => 50000, 'km_arrivee' => 50060, 'heure_depart' => '06:00', 'heure_arrivee' => '15:00', 'sites' => []]);

        $alerts = \App\Domain\Fleet\AlertCenter::all([$this->vehicle->district_id])->where('type', 'sortie_duree');

        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('9h00', $alerts->first()['message']);
        $this->assertStringContainsString('⚠', \App\Filament\Pages\SaisieTournee::dureeMessage('06:00', '15:00', []));
        $this->assertStringNotContainsString('⚠', \App\Filament\Pages\SaisieTournee::dureeMessage('07:30', '15:00', [['h_arrivee' => '09:00', 'h_depart' => '09:30']]));
    }

    public function test_arrival_hour_cannot_precede_departure_hour(): void
    {
        try {
            $this->entry(['km_depart' => 50000, 'heure_depart' => '10:00', 'heure_arrivee' => '09:00', 'sites' => []]);
            $this->fail('Une erreur était attendue.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('heure_arrivee', $e->errors());
        }
    }

    public function test_carnet_has_one_line_per_leg_with_km_a_and_b(): void
    {
        $this->fullTour();

        $data = CarnetDeBord::build($this->vehicle, CarbonImmutable::parse('2026-10-01'));
        $rows = $data['rows'];

        $this->assertCount(3, $rows);   // MEAGUI → CSR A → CSU B → MEAGUI
        $this->assertSame(['MEAGUI', 'CSR A', 50000, 50020, 20], [$rows[0]['lieu_depart'], $rows[0]['lieu_arrivee'], $rows[0]['km_depart'], $rows[0]['km_arrivee'], $rows[0]['distance']]);
        $this->assertSame(['CSR A', 'CSU B', 50020, 50040, 20], [$rows[1]['lieu_depart'], $rows[1]['lieu_arrivee'], $rows[1]['km_depart'], $rows[1]['km_arrivee'], $rows[1]['distance']]);
        $this->assertSame(['CSU B', 'MEAGUI', 50040, 50060, 20], [$rows[2]['lieu_depart'], $rows[2]['lieu_arrivee'], $rows[2]['km_depart'], $rows[2]['km_arrivee'], $rows[2]['distance']]);
        $this->assertSame('07:30', $rows[0]['heure_depart']);
        $this->assertSame('09:00', $rows[0]['heure_arrivee']);   // arrivée au premier site
        $this->assertSame('09:40', $rows[1]['heure_depart']);    // départ du premier site
        $this->assertSame('11:15', $rows[1]['heure_arrivee']);
        $this->assertSame('12:00', $rows[2]['heure_depart']);
        $this->assertSame('15:45', $rows[2]['heure_arrivee']);
        $this->assertSame(['14/10/2026', '', ''], array_column($rows, 'date'));   // la date n'apparaît que sur le premier tronçon
        $this->assertSame(60, $data['totals']['distance']);
    }

    public function test_fuel_is_counted_once_per_sortie(): void
    {
        $this->fullTour();
        $data = CarnetDeBord::build($this->vehicle, CarbonImmutable::parse('2026-10-01'));

        $this->assertEquals(20, $data['totals']['litres']);
        $this->assertEquals(14000, $data['totals']['montant']);
        $this->assertNull($data['rows'][1]['litres']);
    }

    public function test_other_months_are_left_out_and_exports_are_produced(): void
    {
        $this->fullTour();

        $this->assertSame([], CarnetDeBord::build($this->vehicle, CarbonImmutable::parse('2026-09-01'))['rows']);
        $this->assertSame('carnet-de-bord-D55031-2026-10.pdf', CarnetDeBord::filename($this->vehicle, CarbonImmutable::parse('2026-10-01'), 'pdf'));

        $pdf = CarnetDeBord::pdf($this->vehicle, CarbonImmutable::parse('2026-10-01'));
        $this->assertStringContainsString('attachment', (string) $pdf->headers->get('content-disposition'));
        $xlsx = CarnetDeBord::xlsx($this->vehicle, CarbonImmutable::parse('2026-10-01'));
        $this->assertFileExists($xlsx->getFile()->getPathname());
        $this->assertGreaterThan(1000, filesize($xlsx->getFile()->getPathname()));
    }
}
