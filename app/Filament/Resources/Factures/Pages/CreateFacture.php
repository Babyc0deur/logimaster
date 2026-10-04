<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\FactureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacture extends CreateRecord
{
    protected static string $resource = FactureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['cree_par' => auth()->id(), 'statut' => 'brouillon'];
    }
}
