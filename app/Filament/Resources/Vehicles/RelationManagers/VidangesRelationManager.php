<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VidangesRelationManager extends RelationManager
{
    protected static string $relationship = 'vidanges';

    protected static ?string $title = 'Vidanges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('d/m/Y')->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => \App\Filament\Resources\Vidanges\Schemas\VidangeForm::TYPES[$state] ?? $state),
                TextColumn::make('km')->numeric(thousandsSeparator: ' ')->suffix(' km'),
                TextColumn::make('prochain_km')->label('Prochaine à')->numeric(thousandsSeparator: ' ')->suffix(' km')->placeholder('—'),
                TextColumn::make('prestataire')->placeholder('—'),
                TextColumn::make('montant')->money('XOF')->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->money('XOF')->label('Total')),
            ]);
    }
}
