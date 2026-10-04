<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorisations des ressources Filament : une politique par modèle, adossée aux permissions du module
 * (view_/create_/update_/delete_{module}), comme l'API. Le périmètre (district) est assuré par le tenant Filament.
 */
abstract class ModulePolicy
{
    /** Module de permission (ex. « vehicles » → view_vehicles, create_vehicles…). */
    protected string $module;

    protected function allows(User $user, string $action): bool
    {
        return $user->can("{$action}_{$this->module}");
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, $record = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, $record = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, $record = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function replicate(User $user, $record = null): bool
    {
        return $this->allows($user, 'create');
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function restore(User $user, $record = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function forceDelete(User $user, $record = null): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
