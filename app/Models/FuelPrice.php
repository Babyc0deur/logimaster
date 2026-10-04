<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Historique des prix du carburant (FCFA / L) par type, valable à partir de date_effet. */
class FuelPrice extends Model
{
    use HasUuids;

    protected $fillable = ['type_carburant', 'prix', 'date_effet'];

    protected function casts(): array
    {
        return ['date_effet' => 'date:Y-m-d', 'prix' => 'decimal:2'];
    }

    public const TYPES = ['diesel' => 'Gasoil', 'essence' => 'Essence', 'hybride' => 'Hybride'];

    /** Prix en vigueur à $date pour un type de carburant (null si aucun prix n'est défini). */
    public static function current(?string $type, $date = null): ?float
    {
        if (! $type) {
            return null;
        }
        $price = static::where('type_carburant', $type)
            ->whereDate('date_effet', '<=', $date ?? today())
            ->orderByDesc('date_effet')->value('prix');

        return $price !== null ? (float) $price : null;
    }
}
