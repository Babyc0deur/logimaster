<?php

namespace App\Filament\Resources\Circuits\Pages;

use App\Filament\Concerns\SyncsCircuitEtapes;
use App\Filament\Resources\Circuits\CircuitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCircuit extends CreateRecord
{
    use SyncsCircuitEtapes;

    protected static string $resource = CircuitResource::class;

    protected function afterCreate(): void
    {
        $this->saveEtapes($this->data['etapes'] ?? []);
    }
}
