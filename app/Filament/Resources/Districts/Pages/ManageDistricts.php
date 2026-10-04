<?php

namespace App\Filament\Resources\Districts\Pages;

use App\Filament\Resources\Districts\DistrictResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDistricts extends ManageRecords
{
    protected static string $resource = DistrictResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => $data + DistrictResource::technicalFields())];
    }
}
