<?php

namespace App\Filament\Resources\Pres\Pages;

use App\Filament\Resources\Pres\PresResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePres extends ManageRecords
{
    protected static string $resource = PresResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
