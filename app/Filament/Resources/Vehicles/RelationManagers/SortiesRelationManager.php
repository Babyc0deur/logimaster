<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\SortieVehicule;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Onglet « Historique des sorties » : période, motif, km départ/arrivée, distance, consommation. */
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
                TextColumn::make('driver.nom_complet')->label('Chauffeur')->placeholder('—'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => SortieVehicule::MOTIFS[$state] ?? $state),
                TextColumn::make('km_depart')->label('Km départ')->numeric(thousandsSeparator: ' '),
                TextColumn::make('km_arrivee')->label('Km arrivée')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('distance')->label('Distance')->suffix(' km')->placeholder('—'),
                TextColumn::make('consommation_reelle')->label('Conso (L/100)')->placeholder('—'),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => SortieVehicule::STATUTS[$state] ?? $state),
            ])
            ->filters([
                SelectFilter::make('motif')->options(SortieVehicule::MOTIFS),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_sortie', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_sortie', '<=', $v))),
            ]);
    }
}
