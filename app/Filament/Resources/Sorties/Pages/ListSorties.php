<?php

namespace App\Filament\Resources\Sorties\Pages;

use App\Filament\Resources\Sorties\SortieVehiculeResource;
use App\Filament\Support\TableExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSorties extends ListRecords
{
    protected static string $resource = SortieVehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [...TableExport::actions('sorties', 'Sorties de véhicules'), CreateAction::make()];
    }
}
