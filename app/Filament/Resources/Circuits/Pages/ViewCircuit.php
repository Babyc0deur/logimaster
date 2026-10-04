<?php

namespace App\Filament\Resources\Circuits\Pages;

use App\Filament\Resources\Circuits\CircuitResource;
use App\Filament\Resources\Circuits\Widgets\CircuitStats;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCircuit extends ViewRecord
{
    protected static string $resource = CircuitResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier')];
    }

    protected function getFooterWidgets(): array
    {
        return [CircuitStats::class];
    }
}
