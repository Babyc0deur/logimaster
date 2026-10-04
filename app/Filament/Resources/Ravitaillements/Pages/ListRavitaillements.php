<?php

namespace App\Filament\Resources\Ravitaillements\Pages;

use App\Filament\Resources\Ravitaillements\RavitaillementResource;
use App\Filament\Support\TableExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRavitaillements extends ListRecords
{
    protected static string $resource = RavitaillementResource::class;

    protected function getHeaderActions(): array
    {
        return [...TableExport::actions('ravitaillements', 'Ravitaillements'), CreateAction::make()->label('Ajout rapide')];
    }
}
