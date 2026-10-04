<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Domain\Fleet\AlertLevel;
use App\Models\FuelPrice;
use App\Models\Vehicle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public const STATUTS = [
        'disponible' => 'Disponible',
        'en_mission' => 'En mission',
        'en_maintenance' => 'Immobilisé',
        'hors_service' => 'Hors service',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('immatriculation')
            ->columns([
                TextColumn::make('immatriculation')->label('Immatriculation')->searchable()->sortable()->weight('bold'),
                TextColumn::make('marque')->label('Marque / Modèle')
                    ->formatStateUsing(fn (Vehicle $r) => trim("{$r->marque} {$r->modele}"))
                    ->searchable(['marque', 'modele'])->sortable(),
                TextColumn::make('type_vehicule')->label('Type')->badge()->sortable(),
                TextColumn::make('type_carburant')->label('Carburant')
                    ->formatStateUsing(fn ($state) => FuelPrice::TYPES[$state] ?? $state)->sortable(),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUTS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'disponible' => 'success', 'en_mission' => 'warning', 'en_maintenance' => 'danger', default => 'gray',
                    })->sortable(),
                TextColumn::make('district.name')->label("District d'affectation")->sortable(),
                TextColumn::make('appartenance')->label('Appartenance')
                    ->formatStateUsing(fn ($state) => Vehicle::APPARTENANCES[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('km_actuel')->label('Kilométrage')->numeric(thousandsSeparator: ' ')->suffix(' km')->sortable(),
                TextColumn::make('km_vidange')->label('Prochaine vidange')
                    ->formatStateUsing(fn (Vehicle $r) => $r->km_vidange ? number_format($r->km_vidange, 0, ',', ' ').' km' : '—')
                    ->description(fn (Vehicle $r) => $r->km_vidange ? ($r->km_vidange - $r->km_actuel).' km restants' : null)
                    ->badge()
                    ->color(fn (Vehicle $r) => AlertLevel::forKm($r->km_vidange !== null ? $r->km_vidange - $r->km_actuel : null)->color())
                    ->sortable(),
                TextColumn::make('date_ct')->label('CT')->date('d/m/Y')
                    ->color(fn (Vehicle $r) => $r->date_ct ? AlertLevel::forDays((int) today()->diffInDays($r->date_ct, false))->color() : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_assurance')->label('Assurance')->date('d/m/Y')
                    ->color(fn (Vehicle $r) => $r->date_assurance ? AlertLevel::forDays((int) today()->diffInDays($r->date_assurance, false))->color() : null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(self::STATUTS)->multiple(),
                SelectFilter::make('type_vehicule')->label('Type')
                    ->options(fn () => Vehicle::query()->whereNotNull('type_vehicule')->distinct()->pluck('type_vehicule', 'type_vehicule')->all()),
                SelectFilter::make('type_carburant')->label('Carburant')->options(FuelPrice::TYPES),
                SelectFilter::make('appartenance')->options(Vehicle::APPARTENANCES),
                SelectFilter::make('marque')->options(fn () => Vehicle::query()->whereNotNull('marque')->distinct()->pluck('marque', 'marque')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Voir'),
                EditAction::make()->label('Modifier'),
            ])
            ->recordUrl(fn (Vehicle $record) => \App\Filament\Resources\Vehicles\VehicleResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
