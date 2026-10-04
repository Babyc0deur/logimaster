<?php

namespace Tests\Feature;

use App\Domain\Indicators\IndicatorService;
use App\Filament\Pages\Dashboard;
use App\Models\District;
use App\Models\User;
use Database\Seeders\LogimasterRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Models\IndicatorSnapshot;
use App\Models\Pres;
use App\Models\Region;
use App\Support\DashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_configuration_filters_default_to_the_current_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10'));
        [$from, $until] = DashboardFilters::period(null);
        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-10-10', $until->toDateString());
        $this->assertSame('2026-10', DashboardFilters::defaultMonthKey());
        $this->assertSame('2026-10', array_key_first(DashboardFilters::monthOptions(12)));
        $this->assertCount(12, DashboardFilters::monthOptions(12));
    }

    public function test_configured_period_is_the_default_everywhere(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10'));
        config(['logimaster.default_period' => ['from' => '2025-05-01', 'until' => '2025-10-31']]);

        [$from, $until] = DashboardFilters::period(null);
        $this->assertSame(['2025-05-01', '2025-10-31'], [$from->toDateString(), $until->toDateString()]);
        $this->assertSame('2025-10', DashboardFilters::defaultMonthKey());
        $this->assertSame(['2025-10', '2025-09', '2025-08', '2025-07', '2025-06', '2025-05'], array_keys(DashboardFilters::monthOptions(24)));
        $this->assertSame('2025-10-01', DashboardFilters::indicatorMonth(null)->toDateString());
        $this->assertSame('2025-09-01', DashboardFilters::month(['periode' => '2025-09'])[0]->toDateString());

        // un filtre choisi par l'utilisateur l'emporte toujours
        [$from] = DashboardFilters::period(['date_from' => '2025-07-15']);
        $this->assertSame('2025-07-15', $from->toDateString());
    }

    public function test_dashboard_form_opens_on_the_configured_period(): void
    {
        $this->seed(LogimasterRoleSeeder::class);
        config(['logimaster.default_period' => ['from' => '2025-05-01', 'until' => '2025-10-31']]);
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $district = District::create(['region_id' => $region->id, 'name' => 'D', 'sync_id' => 'D', 'sync_password_hash' => 'x']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        Filament::setTenant($district);

        Livewire::test(Dashboard::class)
            ->assertSet('filters.date_from', '2025-05-01 00:00:00')
            ->assertSet('filters.date_until', '2025-10-31 23:59:59');
    }

    public function test_indicator_history_follows_the_displayed_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10'));
        $region = Region::create(['pres_id' => Pres::create(['name' => 'PRES'])->id, 'name' => 'R']);
        $district = District::create(['region_id' => $region->id, 'name' => 'D', 'sync_id' => 'D', 'sync_password_hash' => 'x']);
        foreach (['2025-05-01', '2025-10-01'] as $period) {
            IndicatorSnapshot::create(['district_id' => $district->id, 'indicator_key' => 'distance_totale', 'period' => $period, 'value' => 100]);
        }

        $service = app(IndicatorService::class);
        $this->assertSame([], array_values($service->history([$district->id], 'distance_totale', 12)), 'à partir du mois courant : rien');
        $rows = $service->history([$district->id], 'distance_totale', 12, CarbonImmutable::parse('2025-10-01'));
        $this->assertSame(['2025-05', '2025-10'], collect($rows)->pluck('period')->sort()->values()->all());
    }
}
