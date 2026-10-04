<?php

namespace App\Filament\Resources\Chronogrammes\Pages;

use App\Filament\Resources\Chronogrammes\ChronogrammeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChronogramme extends EditRecord
{
    protected static string $resource = ChronogrammeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
