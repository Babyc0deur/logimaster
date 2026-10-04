<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\FactureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFacture extends ViewRecord
{
    protected static string $resource = FactureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...FactureResource::workflowActions(),
            EditAction::make()->visible(fn () => $this->getRecord()->isEditable() && auth()->user()->can('update_factures')),
        ];
    }
}
