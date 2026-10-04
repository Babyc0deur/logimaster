<?php

namespace App\Policies;

use App\Models\User;

/** Rapports et envois planifiés : consultation (view_reports), génération/gestion (create_reports). */
class ReportPolicy extends ModulePolicy
{
    protected string $module = 'reports';

    protected function allows(User $user, string $action): bool
    {
        return $user->can($action === 'view' ? 'view_reports' : 'create_reports');
    }
}
