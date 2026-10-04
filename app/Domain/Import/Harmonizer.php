<?php

namespace App\Domain\Import;

use Illuminate\Support\Str;

/**
 * Harmonisation des données saisies à la main dans les classeurs : une seule écriture par marque, bailleur, type d'ESPC…
 * pour que les filtres, les regroupements et les rapports de LogiMaster comptent la même chose sous le même nom.
 */
final class Harmonizer
{
    /** Écritures fautives ou variantes de marques. */
    private const BRANDS = [
        'TOYATA' => 'TOYOTA', 'TOTOTA' => 'TOYOTA', 'TOYOYA' => 'TOYOTA', 'TOYOTTA' => 'TOYOTA',
        'MITSUBICHI' => 'MITSUBISHI', 'MITSUBUSHI' => 'MITSUBISHI', 'HUNDAI' => 'HYUNDAI', 'HYNDAI' => 'HYUNDAI', 'NISAN' => 'NISSAN',
    ];

    /** Marques connues : le premier mot d'un libellé « TOYOTA HIACE » est la marque, le reste le modèle. */
    private const KNOWN_BRANDS = ['TOYOTA', 'FORD', 'NISSAN', 'MITSUBISHI', 'MAZDA', 'ISUZU', 'HYUNDAI', 'KIA', 'RENAULT', 'PEUGEOT', 'YAMAHA', 'HONDA', 'SUZUKI', 'MERCEDES', 'VOLKSWAGEN', 'MAN', 'IVECO', 'DACIA', 'LAND', 'JMC', 'FOTON', 'TVS', 'SANILI'];

    private const MODELS = ['HYACE' => 'HIACE', 'HIACE' => 'HIACE', 'HARBODY' => 'HARDBODY', 'FOUGONNETTE' => 'FOURGONNETTE', 'FOURGONETTE' => 'FOURGONNETTE'];

    /** Bailleurs : clé = écriture réduite (majuscules, sans espace ni ponctuation). */
    private const FUNDERS = [
        'PNLP' => 'PNLP', 'PROGRAMMENATIONALDELUTTECONTRELEPALUDISME' => 'PNLP',
        'FM' => 'FONDS MONDIAL', 'FONDMONDIAL' => 'FONDS MONDIAL', 'FONDSMONDIAL' => 'FONDS MONDIAL', 'UCPFM' => 'FONDS MONDIAL',
        'UCPFONDMONDIAL' => 'FONDS MONDIAL', 'UCPFONDSMONDIAL' => 'FONDS MONDIAL', 'FONDMONDIAL1' => 'FONDS MONDIAL',
        'UCPBM' => 'UCP BM', 'GAVI' => 'GAVI', 'USAID' => 'USAID', 'DCPEV' => 'DCPEV', 'PEV' => 'DCPEV',
        'UCPSWEDD' => 'UCP SWEDD', 'SWEDD' => 'UCP SWEDD', 'DIEM' => 'DIEM', 'APROSAM' => 'APROSAM',
    ];

    /** Préfixes de dénomination → type d'établissement (du plus précis au plus général). */
    private const FACILITY_TYPES = [
        'CSU-DM' => 'CSU', 'CSR-DM' => 'CSR', 'CSR-D' => 'CSR', 'CSUI' => 'CSU', 'CSU' => 'CSU', 'CSR' => 'CSR', 'CHR' => 'CHR', 'CHU' => 'CHU',
        'HG' => 'HG', 'HOPITAL GENERAL' => 'HG', 'PMI' => 'PMI', 'FSU COM' => 'FSU COM', 'FSUCOM' => 'FSU COM', 'FSU' => 'FSU',
        'DR' => 'DR', 'DISPENSAIRE' => 'DISPENSAIRE', 'MATERNITE' => 'MATERNITE', 'INFIRMERIE' => 'INFIRMERIE', 'CMS' => 'CMS', 'CMA' => 'CMA',
        'ESPC' => 'ESPC', 'DEPARTEMENT' => 'DISTRICT', 'DISTRICT' => 'DISTRICT', 'DS' => 'DISTRICT', 'DR-' => 'DR',
        'SSSUSAJ' => 'SSSU', 'SSSU' => 'SSSU', 'SSUSAJ' => 'SSU', 'SSU' => 'SSU', 'CSUS' => 'CSU', 'CSUCOM' => 'CSU', 'CM' => 'CM',
        'POLYCLINIQUE' => 'CLINIQUE', 'CLINIQUE' => 'CLINIQUE', 'GRAND CLINIQUE' => 'CLINIQUE', 'ESPACE MEDICAL' => 'CLINIQUE', 'ONG' => 'ONG',
        'CENTRE' => 'CENTRE DE SOINS', 'EPHD' => 'EPH', 'EPHR' => 'EPH', 'DDS' => 'DISTRICT', 'MAC' => 'MAC', 'NPSP' => 'NPSP', 'INHP' => 'INHP',
        'DEPOT' => 'DEPOT', 'HOPITAL' => 'HOPITAL', 'PHARMACIE' => 'PHARMACIE',
        'SSSUSAJ' => 'SSSU', 'SSSU' => 'SSSU', 'SSUSAJ' => 'SSU', 'SSU' => 'SSU', 'CSUS' => 'CSU', 'CSUCOM' => 'CSU', 'CM' => 'CM',
        'POLYCLINIQUE' => 'CLINIQUE', 'CLINIQUE' => 'CLINIQUE', 'GRAND CLINIQUE' => 'CLINIQUE', 'ESPACE MEDICAL' => 'CLINIQUE', 'ONG' => 'ONG',
        'CENTRE' => 'CENTRE DE SOINS', 'EPHD' => 'EPH', 'EPHR' => 'EPH', 'DDS' => 'DISTRICT', 'MAC' => 'MAC', 'NPSP' => 'NPSP', 'INHP' => 'INHP',
        'DEPOT' => 'DEPOT', 'HOPITAL' => 'HOPITAL', 'PHARMACIE' => 'PHARMACIE',
    ];

    /** Nom de personne, bénéficiaire ou prestataire : majuscules, espaces simples (« kouassi  yao » → « KOUASSI YAO »). */
    public static function person(?string $name): ?string
    {
        $name = self::upper($name);

        return $name === '' ? null : $name;
    }

    public static function squish(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', (string) $text)));
    }

    public static function upper(?string $text): string
    {
        return mb_strtoupper(self::squish($text));
    }

    private static function reduce(string $text): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(Str::ascii($text)));
    }

    /** « d54 0116 », « D 54 0116 » → « D54 0116 » n'est pas déduisible : on ne garde que majuscules et espaces simples. */
    public static function plate(?string $plate): string
    {
        return self::upper(preg_replace('/[^\p{L}\p{N} ]+/u', ' ', (string) $plate));
    }

    /** @return array{0: ?string, 1: ?string} [marque, modèle] */
    public static function vehicle(?string $brand, ?string $model): array
    {
        $brand = self::upper($brand);
        $model = self::upper($model);
        $words = $brand === '' ? [] : explode(' ', preg_replace('/^([A-Z]+)-(?=[A-Z])/u', '$1 ', $brand));
        $words = array_map(fn ($w) => self::BRANDS[$w] ?? $w, $words);
        $fixModel = fn (string $m) => implode(' ', array_map(fn ($w) => self::MODELS[$w] ?? $w, $m === '' ? [] : explode(' ', $m)));

        // « TOYOTA HIACE » saisi dans la colonne Marque : on sépare marque et modèle.
        if (count($words) > 1 && in_array($words[0], self::KNOWN_BRANDS, true)) {
            $rest = implode(' ', array_slice($words, 1));
            $model = $model === '' || $model === $rest ? $rest : (str_contains($model, $rest) ? $model : trim($rest.' '.$model));
            $words = [$words[0]];
        }
        $brand = implode(' ', $words);
        $model = $fixModel($model);

        return [$brand !== '' ? $brand : null, $model !== '' ? $model : null];
    }

    public static function funder(?string $funder): ?string
    {
        $name = self::upper($funder);
        if ($name === '') {
            return null;
        }

        $key = self::reduce($name);

        return self::FUNDERS[$key] ?? (str_starts_with($key, 'PNLP') ? 'PNLP' : $name);
    }

    /** Nom d'ESPC : majuscules, espaces et tirets propres (« CSR-DM  PUBLIC de X » → « CSR-DM PUBLIC DE X »). */
    public static function facilityName(?string $name): string
    {
        $name = self::upper($name);
        $name = preg_replace('/\s*-\s*/u', '-', $name);

        return trim(preg_replace('/\s+/u', ' ', $name), " -\t");
    }

    public static function facilityType(?string $name): ?string
    {
        $name = self::facilityName($name);
        foreach (self::FACILITY_TYPES as $prefix => $type) {
            if ($name === $prefix || str_starts_with($name, $prefix.' ') || str_starts_with($name, $prefix.'-')) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Nom de circuit : « 1 », « C1 », « CIR1 », « circuit  1 », « CIRCUT 2 », « CIR1: ANANDA » → « CIRCUIT 1 » (« CIRCUIT 1 - ANANDA »).
     * Les autres noms (axes, cantons, villages) restent tels quels, en majuscules.
     */
    public static function circuitName(?string $name): string
    {
        $name = self::upper($name);
        if (preg_match('/^(?:CIRCUIT|CIRCUT|CIRUIT|CIUCUIT|CIRCUITS|CIRC|CIR|CR|C)?\s*[-_.]?\s*N?°?\s*(\d{1,2})\s*(?:[:\-]\s*(.+))?$/u', $name, $m)) {
            return 'CIRCUIT '.(int) $m[1].(isset($m[2]) && trim($m[2]) !== '' ? ' - '.trim($m[2]) : '');
        }

        return $name;
    }

    /**
     * Faux circuits : motifs ou destinations saisis dans la colonne « Nom du circuit » (programme, garage, district…),
     * reconnaissables à leur nom et à leur seul ou second site.
     */
    public static function isRoute(?string $name, int $sites): bool
    {
        $key = self::key($name);
        $notRoutes = ['PNLP', 'DCPEV', 'DIEMP', 'NPSP', 'PEV', 'GARAGE', 'RECUP', 'ABIDJAN', 'AUTREDEPLACEMENT', 'DISTRICT', 'DISTRICTBASSAM',
            'DISTRICTSANITAIREGBBASSAM', 'GAVI', 'USAID', 'FONDSMONDIAL', 'DIRECTIONREGIONALE', 'DIRECTIONREGIONALEDELASANTE'];
        if ($key === '') {
            return false;
        }

        if (str_starts_with($key, 'AUTREDEPLACEMENT')) {
            return false; // rubrique « autre déplacement » : jamais un parcours
        }

        return ! ($sites <= 2 && (in_array($key, $notRoutes, true) || preg_match('/^HG[A-Z]+$/', $key)));
    }

    /** Libellés du modèle Excel à remplacer par un vrai nom (« AUTRE SITE (A PRÉCISER) ») : ce ne sont pas des établissements. */
    public static function isPlaceholderSite(?string $name): bool
    {
        return str_contains(self::reduce((string) $name), 'APRECISER');
    }

    /** Clé de comparaison : deux écritures de la même chose donnent la même clé. */
    public static function key(?string $text): string
    {
        return self::reduce((string) $text);
    }
}
