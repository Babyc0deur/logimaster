<?php

namespace App\Filament\Resources\Livraisons\Pages;

use App\Filament\Resources\Livraisons\LivraisonResource;
use App\Filament\Resources\Livraisons\Widgets\LivraisonStats;
use App\Filament\Resources\Livraisons\Widgets\NonLivrees;
use App\Filament\Support\TableExport;
use Filament\Resources\Pages\ListRecords;

class ListLivraisons extends ListRecords
{
    protected static string $resource = LivraisonResource::class;

    protected function getHeaderWidgets(): array
    {
        return [LivraisonStats::class, NonLivrees::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('circuits')->label('Vue circuit')->icon('heroicon-o-map')->color('gray')
                ->url(\App\Filament\Pages\SuiviCircuits::getUrl()),
            ...TableExport::actions('livraisons', 'Suivi des livraisons ESPC'),
        ];
    }
}
