<?php

namespace App\Filament\Pages;

use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorService;
use App\Filament\Concerns\ScopeFilters;
use App\Filament\Pages\Indicators\Widgets\IndicatorDetail;
use App\Filament\Pages\Indicators\Widgets\IndicatorHistory;
use App\Filament\Widgets\IndicatorCards;
use App\Support\DashboardFilters;
use App\Support\IndicatorViewData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Ancienne page des indicateurs DDKM : fusionnée dans le tableau de bord ; l'adresse reste valable et y renvoie (filtres conservés). */
class Indicators extends BaseDashboard
{
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(Dashboard::getUrl(['filters' => (array) request()->query('filters', [])]).'#indicateur-detail');
    }

    use HasFiltersForm, ScopeFilters;

    protected static string $routePath = 'indicateurs';

    protected static ?string $slug = 'indicateurs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Indicateurs DDKM';

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'Indicateurs DDKM';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    public function getWidgets(): array
    {
        return [IndicatorCards::class, IndicatorDetail::class, IndicatorHistory::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtres')->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'xl' => 5])->schema([
                ...self::scopeFilterFields(),
                Select::make('periode')->label('Mois')->native(false)->live()
                    ->default(DashboardFilters::defaultMonthKey())
                    ->options(DashboardFilters::monthOptions(24)),
                Select::make('indicateur')->label('Indicateur')->native(false)->live()
                    ->default('distance_totale')
                    ->options(collect(IndicatorCatalog::all())->map(fn ($m) => $m['label'])->all()),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculer')
                ->label('Recalculer le mois')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn () => auth()->user()->can('create_reports'))
                ->requiresConfirmation()
                ->modalDescription('Recalcule les 9 indicateurs du mois sélectionné pour les districts du périmètre (les calculs sont normalement automatiques chaque nuit).')
                ->action(function () {
                    $ids = DashboardFilters::districtIds($this->filters);
                    $month = DashboardFilters::indicatorMonth($this->filters);
                    $service = app(IndicatorService::class);
                    foreach ($ids as $id) {
                        $service->computeForDistrict($id, $month);
                    }
                    IndicatorViewData::flush();
                    Notification::make()->title(count($ids).' district(s) recalculé(s) — '.$month->translatedFormat('F Y'))->success()->send();
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
