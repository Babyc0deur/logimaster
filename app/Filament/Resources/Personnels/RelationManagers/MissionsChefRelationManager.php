<?php

namespace App\Filament\Resources\Personnels\RelationManagers;

use App\Models\SortieVehicule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MissionsChefRelationManager extends RelationManager
{
    protected static string $relationship = 'sortiesEnTantQueChef';

    protected static ?string $title = 'Missions comme chef de mission';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motif')
            ->defaultSort('date_sortie', 'desc')
            ->columns([
                TextColumn::make('date_sortie')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule'),
                TextColumn::make('driver.nom_complet')->label('Chauffeur')->placeholder('—'),
                TextColumn::make('circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => SortieVehicule::MOTIFS[$state] ?? $state),
                TextColumn::make('distance')->label('Distance')->suffix(' km')->placeholder('—'),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => SortieVehicule::STATUTS[$state] ?? $state),
            ]);
    }
}
