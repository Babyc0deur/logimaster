<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prix du carburant à partir d'octobre 2026 : essence 905 FCFA/L, gasoil 725 FCFA/L.
 * Les prix précédents restent dans l'historique : les sorties et ravitaillements antérieurs gardent leur prix.
 * Idempotente : n'écrase pas un prix déjà saisi pour cette date.
 */
return new class extends Migration
{
    private const PRICES = ['essence' => 905, 'diesel' => 725];

    private const DATE = '2026-10-01';

    public function up(): void
    {
        foreach (self::PRICES as $type => $prix) {
            $exists = DB::table('fuel_prices')->where('type_carburant', $type)->whereDate('date_effet', self::DATE)->exists();
            if (! $exists) {
                DB::table('fuel_prices')->insert([
                    'id' => (string) Str::uuid(), 'type_carburant' => $type, 'prix' => $prix, 'date_effet' => self::DATE,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PRICES as $type => $prix) {
            DB::table('fuel_prices')->where('type_carburant', $type)->whereDate('date_effet', self::DATE)->where('prix', $prix)->delete();
        }
    }
};
