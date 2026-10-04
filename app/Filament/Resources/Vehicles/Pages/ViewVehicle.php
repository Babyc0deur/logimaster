<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Resources\Vehicles\Widgets\VehicleMonthlyChart;
use App\Filament\Resources\Vehicles\Widgets\VehicleStatsOverview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier')];
    }

    protected function getFooterWidgets(): array
    {
        return [VehicleStatsOverview::class, VehicleMonthlyChart::class];
    }
}
