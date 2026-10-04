<?php

namespace App\Filament\Resources\Personnels\Pages;

use App\Filament\Resources\Personnels\PersonnelResource;
use App\Filament\Resources\Personnels\Widgets\PersonnelStatsOverview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPersonnel extends ViewRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier')];
    }

    protected function getFooterWidgets(): array
    {
        return [PersonnelStatsOverview::class];
    }
}
