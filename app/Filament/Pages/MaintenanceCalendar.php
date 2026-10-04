<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\MaintenancePlanner;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Module 5.1 : calendrier mensuel des maintenances (vidanges, CT, assurances, révisions) avec niveaux d'alerte. */
class MaintenanceCalendar extends Page
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_vidanges');
    }

    protected string $view = 'filament.pages.maintenance-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Calendrier maintenance';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Calendrier de maintenance';

    protected static ?string $slug = 'calendrier-maintenance';

    public int $monthOffset = 0;

    public function previousMonth(): void
    {
        $this->monthOffset--;
    }

    public function nextMonth(): void
    {
        $this->monthOffset++;
    }

    public function currentMonth(): void
    {
        $this->monthOffset = 0;
    }

    protected function getViewData(): array
    {
        $ids = [Filament::getTenant()->getKey()];
        $month = CarbonImmutable::now()->startOfMonth()->addMonths($this->monthOffset);
        $planner = app(MaintenancePlanner::class);
        $events = $planner->events($ids, $month, $month->endOfMonth())->groupBy('date');

        $gridStart = $month->startOfWeek();
        $weeks = [];
        for ($d = $gridStart; $d <= $month->endOfMonth()->endOfWeek(); $d = $d->addDay()) {
            $weeks[(int) floor($d->diffInDays($gridStart) / 7)][] = $d;
        }

        return [
            'month' => $month,
            'weeks' => $weeks,
            'events' => $events,
            'alerts' => $planner->alerts($ids)->take(10),
        ];
    }
}
