<?php

namespace App\Filament\Resources\Circuits\Pages;

use App\Filament\Resources\Circuits\CircuitResource;
use App\Filament\Support\ImportExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCircuits extends ListRecords
{
    protected static string $resource = CircuitResource::class;

    protected function getHeaderActions(): array
    {
        return [ImportExportActions::group('circuits', 'circuits', 'Circuits'), CreateAction::make()];
    }
}
