<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RavitaillementsRelationManager extends RelationManager
{
    protected static string $relationship = 'ravitaillements';

    protected static ?string $title = 'Carburant';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('station')
            ->defaultSort('date_ravitaillement', 'desc')
            ->columns([
                TextColumn::make('date_ravitaillement')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('km_compteur')->label('Compteur')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('litres')->suffix(' L')->summarize(Sum::make()->suffix(' L')->label('Total')),
                TextColumn::make('prix_unitaire')->label('Prix/L')->money('XOF'),
                TextColumn::make('montant_total')->label('Montant')->money('XOF'),
                TextColumn::make('station')->placeholder('—'),
                TextColumn::make('anomalie')->badge()->color('danger')->placeholder('—')
                    ->formatStateUsing(fn ($state) => ['surconsommation' => 'Surconsommation', 'km_incoherent' => 'Compteur incohérent'][$state] ?? $state),
            ]);
    }
}
