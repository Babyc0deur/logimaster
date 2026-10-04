<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Support\ImportExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [ImportExportActions::group('drivers', 'chauffeurs', 'Chauffeurs'), CreateAction::make()];
    }
}
