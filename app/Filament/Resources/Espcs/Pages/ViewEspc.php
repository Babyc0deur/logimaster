<?php

namespace App\Filament\Resources\Espcs\Pages;

use App\Filament\Resources\Espcs\EspcResource;
use App\Filament\Resources\Espcs\Widgets\EspcStats;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEspc extends ViewRecord
{
    protected static string $resource = EspcResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Modifier')];
    }

    protected function getHeaderWidgets(): array
    {
        return [EspcStats::class];
    }
}
