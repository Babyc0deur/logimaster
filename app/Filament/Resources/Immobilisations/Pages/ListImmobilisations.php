<?php

namespace App\Filament\Resources\Immobilisations\Pages;

use App\Filament\Resources\Immobilisations\ImmobilisationResource;
use App\Filament\Resources\Immobilisations\Widgets\ImmobilisationStats;
use App\Filament\Resources\Immobilisations\Widgets\ProblematicVehicles;
use App\Filament\Support\TableExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImmobilisations extends ListRecords
{
    protected static string $resource = ImmobilisationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ImmobilisationStats::class];
    }

    protected function getFooterWidgets(): array
    {
        return [ProblematicVehicles::class];
    }

    protected function getHeaderActions(): array
    {
        return [...TableExport::actions('immobilisations', 'Immobilisations'), CreateAction::make()];
    }
}
