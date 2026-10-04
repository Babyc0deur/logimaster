<?php

namespace App\Filament\Pages;

use App\Domain\Fuel\FuelAnalyzer;
use App\Models\Setting;
use App\Models\SortieVehicule;
use App\Support\DashboardFilters;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Module 4.3 : consommation réelle vs théorique, par véhicule / motif / mois. */
class FuelAnalysis extends Page
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_ravitaillements');
    }

    protected string $view = 'filament.pages.fuel-analysis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analyse de consommation';

    protected static string|\UnitEnum|null $navigationGroup = 'Carburant';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Analyse de consommation';

    protected static ?string $slug = 'analyse-consommation';

    public string $periode = '';

    public function mount(): void
    {
        $this->periode = DashboardFilters::defaultMonthKey();
    }

    protected function getViewData(): array
    {
        $ids = [Filament::getTenant()->getKey()];
        $month = CarbonImmutable::createFromFormat('Y-m', $this->periode)->startOfMonth();
        $analyzer = app(FuelAnalyzer::class);
        $series = $analyzer->monthlySeries($ids, 12);

        return [
            'months' => collect(DashboardFilters::monthOptions(12)),
            'vehicles' => $analyzer->perVehicle($ids, $month, $month->endOfMonth()),
            'motifs' => $analyzer->byMotif($ids, $month, $month->endOfMonth())
                ->map(fn ($v, $k) => $v + ['label' => SortieVehicule::MOTIFS[$k] ?? 'Non affecté']),
            'series' => $series,
            'seuil' => Setting::get('seuil_surconsommation'),
            'maxConso' => max(1, collect($series)->max(fn ($s) => max($s['reelle'] ?? 0, $s['theorique'] ?? 0))),
        ];
    }
}
