<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RoleForm::fill($data, $this->getRecord()->permissions()->pluck('name')->all());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $permissions = RoleForm::extract($data);
        $record->update($data);
        $record->syncPermissions($permissions);

        return $record;
    }
}
