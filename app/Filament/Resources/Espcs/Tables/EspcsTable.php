<?php

namespace App\Filament\Resources\Espcs\Tables;

use App\Filament\Resources\Espcs\EspcResource;
use App\Filament\Resources\Espcs\Schemas\EspcForm;
use App\Models\Espc;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EspcsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nom')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('circuits:id,nom'))
            ->recordUrl(fn (Espc $record) => EspcResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nom')->searchable()->sortable()->weight('bold'),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => EspcForm::TYPES[$state] ?? $state)->placeholder('—')->sortable(),
                TextColumn::make('circuits')->label('Circuit(s)')->state(fn (Espc $e) => $e->circuits->pluck('nom')->all())->badge()->separator(',')->placeholder('Aucun'),
                TextColumn::make('responsable')->placeholder('—')->searchable(),
                TextColumn::make('telephone')->label('Contact')->placeholder('—'),
                IconColumn::make('gps')->label('GPS')->boolean()->state(fn (Espc $e) => $e->hasGps()),
                TextColumn::make('derniere_livraison')->label('Dernière livraison')->placeholder('—')
                    ->state(fn (Espc $e) => $e->livraisons()->where('statut', 'livre')->max('date_livraison')),
                TextColumn::make('statut')->badge()->color(fn ($state) => $state === 'actif' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')->options(EspcForm::TYPES),
                SelectFilter::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif']),
                TernaryFilter::make('gps')->label('Géolocalisé')->queries(
                    true: fn (Builder $q) => $q->whereNotNull('gps_lat')->whereNotNull('gps_lon'),
                    false: fn (Builder $q) => $q->where(fn ($q) => $q->whereNull('gps_lat')->orWhereNull('gps_lon')),
                    blank: fn (Builder $q) => $q,
                ),
                SelectFilter::make('circuit')->label('Circuit')->options(fn () => \App\Models\Circuit::orderBy('nom')->pluck('nom', 'id'))
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $v) => $q->whereHas('circuits', fn ($c) => $c->where('circuits.id', $v)))),
            ])
            ->recordActions([ViewAction::make()->label('Fiche'), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
