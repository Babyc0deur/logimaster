<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Models\SortieVehicule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Historique des sorties du chauffeur. */
class SortiesRelationManager extends RelationManager
{
    protected static string $relationship = 'sorties';

    protected static ?string $title = 'Historique des sorties';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motif')
            ->defaultSort('date_sortie', 'desc')
            ->columns([
                TextColumn::make('date_sortie')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule'),
                TextColumn::make('circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => SortieVehicule::MOTIFS[$state] ?? $state),
                TextColumn::make('distance')->label('Distance')->suffix(' km')->placeholder('—'),
                TextColumn::make('consommation_reelle')->label('Conso (L/100)')->placeholder('—'),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => SortieVehicule::STATUTS[$state] ?? $state),
            ])
            ->filters([SelectFilter::make('motif')->options(SortieVehicule::MOTIFS)]);
    }
}
