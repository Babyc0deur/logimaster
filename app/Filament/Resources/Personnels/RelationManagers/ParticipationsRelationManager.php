<?php

namespace App\Filament\Resources\Personnels\RelationManagers;

use App\Models\SortieVehicule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParticipationsRelationManager extends RelationManager
{
    protected static string $relationship = 'sortiesEnTantQuePassager';

    protected static ?string $title = 'Participations comme passager';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motif')
            ->defaultSort('date_sortie', 'desc')
            ->columns([
                TextColumn::make('date_sortie')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule'),
                TextColumn::make('chefMission.nom_complet')->label('Chef de mission')->placeholder('—'),
                TextColumn::make('circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => SortieVehicule::MOTIFS[$state] ?? $state),
                TextColumn::make('distance')->label('Distance')->suffix(' km')->placeholder('—'),
            ]);
    }
}
