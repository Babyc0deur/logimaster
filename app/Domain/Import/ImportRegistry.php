<?php

namespace App\Domain\Import;

use App\Domain\Import\Definitions as D;

class ImportRegistry
{
    /** @return array<string, class-string<ImportDefinition>> */
    public static function all(): array
    {
        return [
            'vehicles' => D\VehicleImport::class,
            'drivers' => D\DriverImport::class,
            'espc' => D\EspcImport::class,
            'circuits' => D\CircuitImport::class,
            'chronogrammes' => D\ChronogrammeImport::class,
        ];
    }

    public static function get(string $key): ImportDefinition
    {
        $class = self::all()[$key] ?? abort(404, 'Jeu de données inconnu.');

        return new $class;
    }
}
