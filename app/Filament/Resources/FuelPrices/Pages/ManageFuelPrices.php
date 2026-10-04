<?php

namespace App\Filament\Resources\FuelPrices\Pages;

use App\Filament\Resources\FuelPrices\FuelPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFuelPrices extends ManageRecords
{
    protected static string $resource = FuelPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
