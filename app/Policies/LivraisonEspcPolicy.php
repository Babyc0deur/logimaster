<?php

namespace App\Policies;

use App\Models\User;

/** Suivi des livraisons : mêmes droits que le chronogramme ; les livraisons naissent des plans (ni création ni suppression). */
class LivraisonEspcPolicy extends ModulePolicy
{
    protected string $module = 'chronogrammes';

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, $record = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
