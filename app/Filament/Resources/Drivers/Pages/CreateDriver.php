<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Drivers\Schemas\DriverForm;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DriverForm::mergeCategories($data, $this->data);
    }
}
