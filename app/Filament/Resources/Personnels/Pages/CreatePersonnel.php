<?php

namespace App\Filament\Resources\Personnels\Pages;

use App\Filament\Resources\Personnels\PersonnelResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonnel extends CreateRecord
{
    protected static string $resource = PersonnelResource::class;
}
