<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\FactureResource;
use App\Filament\Resources\Factures\Widgets\FactureStats;
use App\Filament\Support\TableExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFactures extends ListRecords
{
    protected static string $resource = FactureResource::class;

    protected function getHeaderWidgets(): array
    {
        return [FactureStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [...TableExport::actions('factures', 'Factures'), CreateAction::make()->label('Nouvelle facture')];
    }
}
