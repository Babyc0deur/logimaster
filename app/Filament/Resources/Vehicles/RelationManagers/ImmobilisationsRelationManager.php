<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\Immobilisation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Onglet « Réparations & immobilisations ». */
class ImmobilisationsRelationManager extends RelationManager
{
    protected static string $relationship = 'immobilisations';

    protected static ?string $title = 'Réparations & immobilisations';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('motif')
            ->defaultSort('date_debut', 'desc')
            ->columns([
                TextColumn::make('date_debut')->label('Début')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Fin')->date('d/m/Y')->placeholder('En cours'),
                TextColumn::make('duree_jours')->label('Durée')->suffix(' j'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => Immobilisation::MOTIFS[$state] ?? $state),
                TextColumn::make('prestataire')->placeholder('—'),
                TextColumn::make('montant')->money('XOF')->summarize(Sum::make()->money('XOF')->label('Total')),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => Immobilisation::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'terminee' ? 'success' : 'warning'),
            ]);
    }
}
