<?php

namespace App\Models\Concerns;

/**
 * Pour les tables synchronisables : une saisie faite au bureau (Filament, API) appartient au district.
 * Les saisies mobiles/desktop fournissent leur propre owner_type / owner_id.
 */
trait DefaultsOwnerToDistrict
{
    public static function bootDefaultsOwnerToDistrict(): void
    {
        static::creating(function ($model) {
            $model->owner_type ??= 'district';
            $model->owner_id ??= $model->district_id;
        });
    }
}
