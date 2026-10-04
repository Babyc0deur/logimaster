<?php

namespace App\Filament\Resources\Espcs\RelationManagers;

use App\Models\LivraisonEspc;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Tableau des livraisons du site (planifiées, livrées, retards, raisons). */
class LivraisonsRelationManager extends RelationManager
{
    protected static string $relationship = 'livraisons';

    protected static ?string $title = 'Historique des livraisons';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('statut')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('chronogramme.circuit:id,nom')
                ->orderByDesc(\App\Models\Chronogramme::select('date_prevue')->whereColumn('chronogrammes.id', 'livraisons_espc.chronogramme_id')))
            ->columns([
                TextColumn::make('chronogramme.date_prevue')->label('Date prévue')->date('d/m/Y'),
                TextColumn::make('chronogramme.circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => LivraisonEspc::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => ['livre' => 'success', 'non_livre' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('date_livraison')->label('Livrée le')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('lieu_livraison')->label('Lieu')->formatStateUsing(fn ($state) => LivraisonEspc::LIEUX[$state] ?? $state)->placeholder('—'),
                TextColumn::make('retard')->label('Retard')->state(fn (LivraisonEspc $l) => $l->statut === 'livre' ? ($l->retard_jours > 0 ? "{$l->retard_jours} j" : 'Aucun') : '—')
                    ->color(fn (LivraisonEspc $l) => $l->retard_jours > 0 ? 'danger' : null),
                TextColumn::make('raison_non_livraison')->label('Raison')->placeholder('—')->wrap(),
            ])
            ->filters([SelectFilter::make('statut')->options(LivraisonEspc::STATUTS)]);
    }
}
