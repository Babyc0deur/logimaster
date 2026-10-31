<?php

namespace App\Filament\Resources\Enlevements\Pages;

use App\Filament\Resources\Enlevements\EnlevementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnlevements extends ListRecords
{
    protected static string $resource = EnlevementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Enregistrer un enlèvement')];
    }
}
