<?php

namespace App\Filament\Pages;

use App\Domain\Fleet\VehicleSharing;
use App\Models\Vehicle;
use App\Models\VehicleMutualisation;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Activité d'un véhicule : sorties, kilomètres, carburant, autres frais, vidanges et immobilisations, par district.
 * Accessible au district propriétaire (toute l'activité) et au district qui l'utilise en mutualisation (pendant ses périodes).
 */
class ActiviteVehicule extends Page
{
    protected string $view = 'filament.pages.activite-vehicule';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Activité du véhicule';

    protected static ?string $slug = 'activite-vehicule';

    #[Url(as: 'vehicule')]
    public ?string $vehicleId = null;

    public string $du = '';

    public string $au = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_vehicles');
    }

    public function mount(): void
    {
        $vehicle = Vehicle::withoutGlobalScopes()->find($this->vehicleId);   // le véhicule peut appartenir à un autre district que celui affiché
        abort_unless($vehicle && app(VehicleSharing::class)->canView(auth()->user(), $vehicle), 403, 'Ce véhicule n\'est pas visible pour votre district.');

        $this->au = today()->toDateString();
        $this->du = today()->subDays(90)->toDateString();
    }

    public function updatedDu(): void
    {
        $this->du = $this->clean($this->du, today()->subDays(90));
    }

    public function updatedAu(): void
    {
        $this->au = $this->clean($this->au, today());
    }

    private function clean(string $value, CarbonImmutable $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback->toDateString();
    }

    public function getHeading(): string
    {
        $v = Vehicle::withoutGlobalScopes()->find($this->vehicleId);

        return 'Activité du véhicule '.($v?->immatriculation ?? '');
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $user = auth()->user();
        $vehicle = Vehicle::withoutGlobalScopes()->with('district:id,name')->findOrFail($this->vehicleId);
        $sharing = app(VehicleSharing::class);
        $from = CarbonImmutable::parse($this->du);
        $to = CarbonImmutable::parse($this->au);

        return [
            'vehicle' => $vehicle,
            'owner' => $sharing->isOwner($user, $vehicle),
            'periods' => VehicleMutualisation::with('district:id,name')->where('vehicle_id', $vehicle->id)->orderBy('date_debut')->get(),
            'activity' => $sharing->activity($user, $vehicle, $from, $to->lt($from) ? $from : $to),
            'ranges' => $sharing->visibleRanges($user, $vehicle),
        ];
    }
}
