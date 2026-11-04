<?php

namespace App\Policies;

use App\Models\User;

class VehiclePolicy extends ModulePolicy
{
    protected string $module = 'vehicles';

    /** Un véhicule prêté par un autre district se consulte et s'utilise, mais seul le district propriétaire le modifie ou le supprime. */
    private function owns(User $user, $record): bool
    {
        return $record === null || $user->canAccessDistrict($record->district_id);
    }

    public function update(User $user, $record = null): bool
    {
        return parent::update($user, $record) && $this->owns($user, $record);
    }

    public function delete(User $user, $record = null): bool
    {
        return parent::delete($user, $record) && $this->owns($user, $record);
    }

    public function restore(User $user, $record = null): bool
    {
        return parent::restore($user, $record) && $this->owns($user, $record);
    }
}
