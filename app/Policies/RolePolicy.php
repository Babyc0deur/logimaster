<?php

namespace App\Policies;

use App\Models\User;

/** Rôles et permissions : réservés à l'administrateur national. */
class RolePolicy
{
    private function national(User $user): bool
    {
        return $user->isNational();
    }

    public function viewAny(User $user): bool
    {
        return $this->national($user);
    }

    public function view(User $user, $record = null): bool
    {
        return $this->national($user);
    }

    public function create(User $user): bool
    {
        return $this->national($user);
    }

    public function update(User $user, $record = null): bool
    {
        return $this->national($user);
    }

    public function delete(User $user, $record = null): bool
    {
        return $this->national($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->national($user);
    }
}
