<?php

namespace App\Filament\Resources\Personnels\Pages;

use App\Filament\Resources\Personnels\PersonnelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPersonnels extends ListRecords
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [\App\Filament\Support\MobileAccessActions::qr(), CreateAction::make()];
    }
}
