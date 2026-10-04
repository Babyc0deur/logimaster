<?php

namespace App\Filament\Resources\Espcs\Pages;

use App\Filament\Resources\Espcs\EspcResource;
use App\Filament\Support\ImportExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEspcs extends ListRecords
{
    protected static string $resource = EspcResource::class;

    protected function getHeaderActions(): array
    {
        return [ImportExportActions::group('espc', 'espc', 'Établissements sanitaires'), CreateAction::make()];
    }
}
