<?php

namespace App\Filament\Resources\Mutualisations\Pages;

use App\Filament\Resources\Mutualisations\MutualisationResource;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateMutualisation extends CreateRecord
{
    protected static string $resource = MutualisationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant()?->getKey();

        // seul le district propriétaire met un véhicule à disposition, et pas à lui-même
        $vehicle = Vehicle::where('district_id', $tenant)->find($data['vehicle_id'] ?? null);
        if (! $vehicle || ! auth()->user()->canAccessDistrict($vehicle->district_id)) {
            throw ValidationException::withMessages(['data.vehicle_id' => 'Ce véhicule n\'appartient pas à votre district.']);
        }
        if (($data['district_id'] ?? null) === $tenant) {
            throw ValidationException::withMessages(['data.district_id' => 'Choisissez un autre district que le vôtre.']);
        }

        return $data + ['statut' => 'active', 'cree_par' => auth()->id()];
    }
}
