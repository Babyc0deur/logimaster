<?php

namespace App\Filament\Resources\Sorties\Pages;

use App\Filament\Resources\Sorties\SortieVehiculeResource;
use App\Filament\Support\TableExport;
use App\Filament\Pages\SaisieTournee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSorties extends ListRecords
{
    protected static string $resource = SortieVehiculeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...TableExport::actions('sorties', 'Sorties de véhicules'),
            Action::make('tournee')->label('Saisir une tournée')->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn () => auth()->user()->can('update_chronogrammes'))->url(SaisieTournee::getUrl()),
            CreateAction::make(),
        ];
    }
}
