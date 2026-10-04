<?php

$dir = __DIR__ . '/app/Filament/Widgets';
if (!is_dir($dir)) mkdir($dir, 0777, true);

// 1. Stats Overview
$statsContent = <<<'PHP'
<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class FleetStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return [];

        $total = Vehicle::where('district_id', $tenant->id)->count();
        $inMission = Vehicle::where('district_id', $tenant->id)->where('statut', 'en_mission')->count();
        $inMaintenance = Vehicle::where('district_id', $tenant->id)->where('statut', 'en_maintenance')->count();

        return [
            Stat::make('Total Véhicules', $total)
                ->description('Dans votre district')
                ->icon('heroicon-o-truck')
                ->color('info'),
            Stat::make('En Mission', $inMission)
                ->description('Sur le terrain')
                ->icon('heroicon-o-map-pin')
                ->color('warning'),
            Stat::make('En Maintenance', $inMaintenance)
                ->description('Immobilisés')
                ->icon('heroicon-o-wrench')
                ->color('danger'),
        ];
    }
}
PHP;
file_put_contents("$dir/FleetStatsOverview.php", $statsContent);

// 2. Maintenance Alerts Table
$alertsContent = <<<'PHP'
<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceAlerts extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = '⚠️ Alertes Maintenance & Contrôle Technique';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vehicle::query()
                    ->where('district_id', Filament::getTenant()?->id)
                    ->where(function (Builder $query) {
                        $query->whereRaw('km_vidange - km_actuel <= 500')
                              ->orWhere('date_ct', '<=', now()->addDays(30));
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('immatriculation')
                    ->label('Véhicule')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('km_actuel')
                    ->label('Km Actuel')
                    ->numeric(),
                Tables\Columns\TextColumn::make('km_vidange')
                    ->label('Prochaine Vidange')
                    ->numeric()
                    ->color(fn ($record) => ($record->km_vidange - $record->km_actuel <= 500) ? 'danger' : 'success')
                    ->description(fn ($record) => ($record->km_vidange - $record->km_actuel) . ' km restants'),
                Tables\Columns\TextColumn::make('date_ct')
                    ->label('Expiration CT')
                    ->date('d/m/Y')
                    ->color(fn ($record) => ($record->date_ct && $record->date_ct <= now()->addDays(30)) ? 'danger' : 'success'),
            ]);
    }
}
PHP;
file_put_contents("$dir/MaintenanceAlerts.php", $alertsContent);

// 3. Fuel Chart
$chartContent = <<<'PHP'
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Ravitaillement;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

class FuelChart extends ChartWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Dépenses Carburant (30 derniers jours)';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return ['datasets' => [], 'labels' => []];

        $records = Ravitaillement::where('district_id', $tenant->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(montant_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = $records->pluck('date')->map(fn($d) => Carbon::parse($d)->format('d/m'))->toArray();
        $totals = $records->pluck('total')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Carburant (XOF)',
                    'data' => $totals,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
PHP;
file_put_contents("$dir/FuelChart.php", $chartContent);

echo "Widgets générés avec succès.";
