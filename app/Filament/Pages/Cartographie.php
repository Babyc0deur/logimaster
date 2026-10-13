<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\FleetMapSimulation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use App\Models\District;

/** Suivi en temps réel (ex « Cartographie ») du district choisi dans le menu (OpenStreetMap) : les véhicules parcourent leur circuit en simulation, site par site. */
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
        return 'Simulation du déplacement des véhicules sur leurs circuits, district choisi, sa région, ses véhicules et leurs circuits — fond de carte OpenStreetMap.';
    }

    /** Le district choisi dans le menu : sa région (limites et districts voisins), le district, ses véhicules et leurs circuits. */
    public function getViewData(): array
    {
        $district = Filament::getTenant()->loadMissing('region:id,name');
        $limits = District::where('region_id', $district->region_id)->with('region:id,name')->orderBy('name')->get();

        return ['map' => app(FleetMapSimulation::class)->buildMany(collect([$district]), 12, $limits)];
    }
}
