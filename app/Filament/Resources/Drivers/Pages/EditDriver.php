<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Drivers\Schemas\DriverForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DriverForm::mergeCategories($data, $this->data);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Fiche'), DeleteAction::make()];
    }
}
