<?php

namespace App\Filament\Resources\Mutualisations\Pages;

use App\Filament\Resources\Mutualisations\MutualisationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutualisations extends ListRecords
{
    protected static string $resource = MutualisationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Mettre un véhicule à disposition')];
    }
}
