<?php

namespace App\Filament\Resources\Vidanges\Pages;

use App\Filament\Resources\Vidanges\VidangeResource;
use App\Filament\Support\TableExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVidanges extends ListRecords
{
    protected static string $resource = VidangeResource::class;

    protected function getHeaderActions(): array
    {
        return [...TableExport::actions('vidanges', 'Vidanges'), CreateAction::make()];
    }
}
