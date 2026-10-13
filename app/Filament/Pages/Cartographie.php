<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\FleetMapSimulation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Suivi en temps réel (ex « Cartographie ») du district (OpenStreetMap) : les véhicules parcourent leur circuit en simulation, site par site. */
class Cartographie extends Page
{
    protected string $view = 'filament.pages.cartographie';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Suivi en temps réel';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Suivi en temps réel';

    protected static ?string $slug = 'suivi-temps-reel';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    public function getSubheading(): ?string
    {
        return 'Simulation du déplacement des véhicules du district sur leurs circuits — fond de carte OpenStreetMap.';
    }

    public function getViewData(): array
    {
        return ['map' => app(FleetMapSimulation::class)->build(Filament::getTenant())];
    }
}
