<?php

namespace App\Policies;

/** Signalements terrain : visibles avec les véhicules, traités (validés / rejetés) par qui peut modifier les véhicules. */
class SignalementPolicy extends ModulePolicy
{
    protected string $module = 'vehicles';
}
