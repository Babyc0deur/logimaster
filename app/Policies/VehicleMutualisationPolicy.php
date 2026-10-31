<?php

namespace App\Policies;

use App\Models\User;

/**
 * Mutualisation de véhicules : tout utilisateur qui voit les véhicules voit les mises à disposition de son district (prêtées ou
 * empruntées) ; seul le district propriétaire du véhicule les crée, les modifie ou les supprime.
 */
class VehicleMutualisationPolicy extends ModulePolicy
{
    protected string $module = 'vehicles';

    public function view(User $user, $record = null): bool
    {
        return $this->allows($user, 'view') && ($record === null || $this->ownsVehicle($user, $record) || $user->canAccessDistrict($record->district_id));
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function update(User $user, $record = null): bool
    {
        return $this->allows($user, 'update') && ($record === null || $this->ownsVehicle($user, $record));
    }

    public function delete(User $user, $record = null): bool
    {
        return $this->update($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function replicate(User $user, $record = null): bool
    {
        return false;
    }

    private function ownsVehicle(User $user, $record): bool
    {
        return $record->vehicle !== null && $user->canAccessDistrict($record->vehicle->district_id);
    }
}
