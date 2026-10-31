<?php

namespace App\Filament\Resources\Enlevements\Pages;

use App\Filament\Resources\Enlevements\EnlevementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEnlevement extends CreateRecord
{
    protected static string $resource = EnlevementResource::class;

    /** Un enlèvement est un frais de type « Enlèvement ESPC » : il entre dans les autres frais et dans le coût global. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['type' => 'enlevement_espc', 'saisi_par' => auth()->id()];
    }
}
