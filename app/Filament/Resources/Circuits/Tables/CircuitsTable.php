<?php

namespace App\Filament\Resources\Circuits\Tables;

use App\Filament\Resources\Circuits\CircuitResource;
use App\Filament\Resources\Circuits\Schemas\CircuitForm;
use App\Models\Circuit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CircuitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nom')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('espc'))
            ->recordUrl(fn (Circuit $record) => CircuitResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nom')->label('Circuit')->searchable()->sortable()->weight('bold'),
                TextColumn::make('district.name')->label('District')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('espc_count')->label("Nb d'ESPC")->sortable(),
                TextColumn::make('distance_totale')->label('Distance')->suffix(' km')->numeric(1)->sortable()->placeholder('—'),
                TextColumn::make('temps_estime_min')->label('Temps estimé')
                    ->formatStateUsing(fn ($state) => $state ? sprintf('%dh%02d', intdiv((int) $state, 60), (int) $state % 60) : '—')->sortable(),
                TextColumn::make('frequence')->label('Fréquence')->badge()->formatStateUsing(fn ($state) => CircuitForm::FREQUENCES[$state] ?? $state)->placeholder('—'),
                TextColumn::make('respect')->label('Respect')
                    ->state(function (Circuit $c) {
                        $evalues = $c->sorties()->whereNotNull('circuit_respecte')->where('statut', '!=', 'annulee');
                        $n = (clone $evalues)->count();

                        return $n > 0 ? round((clone $evalues)->where('circuit_respecte', true)->count() / $n * 100).' %' : '—';
                    })->badge()->color('gray'),
                TextColumn::make('statut')->badge()->color(fn ($state) => $state === 'actif' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif']),
                SelectFilter::make('frequence')->options(CircuitForm::FREQUENCES),
            ])
            ->recordActions([ViewAction::make()->label('Fiche'), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
