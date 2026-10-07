<?php

namespace App\Domain\Organisation;

use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pôles régionaux (PRES) et régions sanitaires rattachées. Source unique : utilisée par l'installation (OrganisationSeeder)
 * et par la commande organisation:pres, lancée à chaque démarrage (sans effet quand tout est déjà en place).
 */
class PresMapping
{
    /** PRES => régions (noms officiels). */
    public const MAP = [
        "PRES d'Abidjan" => ['Abidjan 1', 'Abidjan 2', 'Agnéby-Tiassa', 'Grands Ponts', 'Mé', 'Sud-Comoé'],
        'PRES de Yamoussoukro' => ['Bélier', 'Marahoué', "N'Zi", 'Gôh', 'Lôh-Djiboua'],
        'PRES de Bouaké' => ['Gbêkê', 'Hambol', 'Béré'],
        'PRES de Korhogo' => ['Poro', 'Tchologo', 'Bagoué'],
        "PRES d'Abengourou" => ['Indénié-Djuablin', 'Moronou', 'Iffou'],
        'PRES de Bondoukou' => ['Gontougo', 'Bounkani'],
        'PRES de Daloa' => ['Haut-Sassandra', 'Worodougou'],
        'PRES de Man' => ['Tonkpi', 'Guémon', 'Cavally'],
        "PRES d'Odienné" => ['Kabadougou', 'Bafing', 'Folon'],
        'PRES de San-Pédro' => ['San-Pédro', 'Gbôklè', 'Nawa'],
    ];

    /** Orthographes de la base différentes des noms officiels (clé normalisée => clé normalisée officielle). */
    private const ALIASES = ['INDENIE DUABLIN' => 'INDENIE DJUABLIN'];

    /** Noms de régions corrigés dans la base (ancien nom exact => nom officiel). */
    public const RENAMES = ['INDENIE DUABLIN' => 'INDENIE-DJUABLIN', 'HAUT SASSANDRA' => 'HAUT-SASSANDRA', 'SAN PEDRO' => 'SAN-PEDRO'];

    /** Noms de districts corrigés dans la base (ancien nom exact => nom officiel). */
    public const DISTRICT_RENAMES = ['SAN PEDRO' => 'SAN-PEDRO'];

    /** Ancien PRES unique, remplacé par les 10 pôles. */
    public const LEGACY = "PRES Côte d'Ivoire";

    public static function key(string $name): string
    {
        $k = Str::upper(Str::ascii($name));
        $k = trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9]+/', ' ', str_replace(["'", '’'], '', $k))));

        return self::ALIASES[$k] ?? $k;
    }

    /** PRES officiel d'une région (nom), null si la région n'est pas dans la liste. */
    public static function presFor(string $region): ?string
    {
        foreach (self::MAP as $pres => $regions) {
            foreach ($regions as $r) {
                if (self::key($r) === self::key($region)) {
                    return $pres;
                }
            }
        }

        return null;
    }

    /**
     * Corrige les noms de régions et de districts, crée les PRES manquants, rattache chaque région à son PRES, supprime l'ancien PRES unique s'il n'a plus de région.
     *
     * @return array{moved: int, unknown: array<int, string>}
     */
    public static function apply(): array
    {
        return DB::transaction(function () {
            $ids = [];
            foreach (array_keys(self::MAP) as $name) {
                $ids[$name] = Pres::firstOrCreate(['name' => $name])->id;
            }
            foreach (self::RENAMES as $from => $to) {
                if (! Region::where('name', $to)->exists()) {
                    Region::where('name', $from)->update(['name' => $to]);
                }
            }
            foreach (self::DISTRICT_RENAMES as $from => $to) {
                if (! District::where('name', $to)->exists()) {
                    District::where('name', $from)->update(['name' => $to]);
                }
            }
            $moved = 0;
            $unknown = [];
            foreach (Region::all() as $region) {
                $pres = self::presFor($region->name);
                if ($pres === null) {
                    $unknown[] = $region->name;

                    continue;
                }
                if ($region->pres_id !== $ids[$pres]) {
                    $region->update(['pres_id' => $ids[$pres]]);
                    $moved++;
                }
            }
            // PRES qui ne figurent plus dans la liste (ancien PRES unique, pôle supprimé) : retirés une fois vides
            Pres::whereNotIn('name', array_keys(self::MAP))->whereDoesntHave('regions')->get()->each->delete();

            return ['moved' => $moved, 'unknown' => $unknown];
        });
    }
}
