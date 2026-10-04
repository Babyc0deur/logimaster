<?php

namespace App\Filament\Resources\Factures\Pages;

use App\Filament\Resources\Factures\FactureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFacture extends EditRecord
{
    protected static string $resource = FactureResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        // Une facture soumise, validée ou payée n'est plus modifiable : on renvoie vers sa fiche.
        if (! $this->getRecord()->isEditable()) {
            $this->redirect(FactureResource::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn () => auth()->user()->can('delete_factures'))];
    }
}
