<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Paramètres clé/valeur (seuils d'alerte, etc.). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'seuil_surconsommation' => 15,   // % d'écart conso réelle/théorique au-delà duquel un véhicule est signalé
        'seuil_surconsommation_sortie' => 20, // % d'écart sur une sortie
        'seuil_vidange_urgent_km' => 100,
        'seuil_vidange_attention_km' => 500,
        'seuil_echeance_urgent_jours' => 7,
        'seuil_echeance_attention_jours' => 30,
        'intervalle_vidange_km' => 5000,
        'immobilisation_alerte_jours' => 7,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting.$key", fn () => static::whereKey($key)->value('value'));

        return $value === null ? ($default ?? static::DEFAULTS[$key] ?? null) : (is_numeric($value) ? $value + 0 : $value);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget("setting.$key");
    }
}
