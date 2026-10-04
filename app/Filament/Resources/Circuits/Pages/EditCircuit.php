<?php

namespace App\Filament\Resources\Circuits\Pages;

use App\Filament\Concerns\SyncsCircuitEtapes;
use App\Filament\Resources\Circuits\CircuitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCircuit extends EditRecord
{
    use SyncsCircuitEtapes;

    protected static string $resource = CircuitResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['etapes' => $this->loadEtapes()];
    }

    protected function afterSave(): void
    {
        $this->saveEtapes($this->data['etapes'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Fiche'), DeleteAction::make()];
    }
}
