<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Domain\Fleet\AlertLevel;
use App\Models\Driver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nom_complet')
            ->recordUrl(fn (Driver $record) => \App\Filament\Resources\Drivers\DriverResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nom_complet')->label('Nom complet')->searchable()->sortable()->weight('bold'),
                TextColumn::make('matricule')->searchable(),
                TextColumn::make('telephone')->label('Téléphone')->placeholder('—')->toggleable(),
                TextColumn::make('district.name')->label('District')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vehiculePrincipal.immatriculation')->label('Véhicule principal')->placeholder('—'),
                TextColumn::make('categorie_permis')->label('Catégories')->badge()->separator(',')->placeholder('—'),
                TextColumn::make('permis_expiration')->label('Validité du permis')
                    ->state(function (Driver $d) {
                        if (! $d->permis_expiration) {
                            return '—';
                        }
                        $days = (int) today()->diffInDays($d->permis_expiration, false);

                        return $days < 0 ? 'Expiré' : ($days <= 30 ? "Expire dans {$days} j" : $d->permis_expiration->format('d/m/Y'));
                    })
                    ->badge()
                    ->color(fn (Driver $d) => $d->permis_expiration ? ((int) today()->diffInDays($d->permis_expiration, false) < 0 ? 'danger' : AlertLevel::forDays((int) today()->diffInDays($d->permis_expiration, false))->color()) : 'gray')
                    ->sortable(),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => Driver::STATUTS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'actif' => 'success', 'conge', 'absent' => 'warning', 'suspendu' => 'danger', default => 'gray' }),
            ])
            ->filters([
                SelectFilter::make('statut')->options(Driver::STATUTS)->multiple(),
                SelectFilter::make('vehicule_principal_id')->label('Véhicule principal')->relationship('vehiculePrincipal', 'immatriculation'),
            ])
            ->recordActions([ViewAction::make()->label('Fiche'), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
