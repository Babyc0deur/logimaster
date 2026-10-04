<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Organisation sanitaire réelle : 1 PRES → 33 régions → 113 districts (codes officiels du classeur Logimaster,
 * onglets « Ali » et « LISTE »). Idempotent. Identifiant de sync = « DS » + code à 3 chiffres ; le mot de passe
 * de sync est aléatoire (inconnu) : à générer par district via POST /api/districts/{id}/sync-credentials/rotate.
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/organisation_ci.json')), true, flags: JSON_THROW_ON_ERROR);
        $pres = Pres::firstOrCreate(['name' => "PRES Côte d'Ivoire"]);
        $unknown = Hash::make(Str::random(32)); // un seul hachage (bcrypt est lent) : personne ne connaît ce mot de passe

        foreach ($data['regions'] as $r) {
            $region = Region::firstOrCreate(['pres_id' => $pres->id, 'name' => $r['name']]);
            foreach ($r['districts'] as $d) {
                District::firstOrCreate(
                    ['sync_id' => sprintf('DS%03d', $d['code'])],
                    ['region_id' => $region->id, 'name' => $d['name'], 'sync_password_hash' => $unknown]
                );
            }
        }
    }
}
