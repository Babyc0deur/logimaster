<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Filament\Resources\Drivers\Widgets\DriverStatsOverview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDriver extends ViewRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier')];
    }

    protected function getFooterWidgets(): array
    {
        return [DriverStatsOverview::class];
    }
}
