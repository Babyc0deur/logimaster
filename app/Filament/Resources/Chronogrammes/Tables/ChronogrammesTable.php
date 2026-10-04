<?php

namespace App\Filament\Resources\Chronogrammes\Tables;

use App\Models\Chronogramme;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ChronogrammesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_prevue', 'asc')
            ->columns([
                TextColumn::make('date_prevue')->label('Date')->date('D d/m/Y')->sortable(),
                TextColumn::make('heure_depart')->label('Heure')->time('H:i'),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->searchable(),
                TextColumn::make('driver.nom_complet')->label('Chauffeur')->searchable(),
                TextColumn::make('circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('motif')->badge(),
                TextColumn::make('validation_statut')->label('Validation')->badge()
                    ->formatStateUsing(fn (?string $state) => Chronogramme::VALIDATIONS[$state ?? 'brouillon'] ?? $state)
                    ->color(fn (?string $state) => match ($state) { 'valide' => 'success', 'soumis' => 'info', 'refuse' => 'danger', default => 'gray' })
                    ->tooltip(fn (Chronogramme $record) => $record->motif_refus),
                TextColumn::make('statut')
                    ->badge()
                    ->formatStateUsing(fn (Chronogramme $record, string $state) => $record->est_en_retard ? 'En retard' : (Chronogramme::STATUTS[$state] ?? $state))
                    ->color(fn (Chronogramme $record) => $record->est_en_retard ? 'danger' : match ($record->statut) {
                        'realisee' => 'success',
                        'reportee' => 'warning',
                        'annulee' => 'gray',
                        default => 'info',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->options(Chronogramme::STATUTS),
                SelectFilter::make('validation_statut')->label('Validation')->options(Chronogramme::VALIDATIONS),
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                SelectFilter::make('driver_id')->label('Chauffeur')->relationship('driver', 'nom_complet'),
                SelectFilter::make('circuit_id')->label('Circuit')->relationship('circuit', 'nom'),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('date_prevue', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('date_prevue', '<=', $d))),
            ])
            ->recordActions([
                Action::make('demarrer')
                    ->label('Démarrer la sortie')
                    ->icon('heroicon-o-play')->color('success')
                    ->visible(fn (Chronogramme $record) => in_array($record->statut, ['planifiee', 'reportee'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Une sortie « en cours » sera créée avec le kilométrage actuel du véhicule.')
                    ->action(function (Chronogramme $record) {
                        $record->demarrer();
                        Notification::make()->title('Sortie démarrée')->success()->send();
                    }),
                Action::make('dupliquer')
                    ->label('Dupliquer')
                    ->icon('heroicon-o-document-duplicate')
                    ->schema([DatePicker::make('date_prevue')->label('Nouvelle date')->required()->native(false)->displayFormat('d/m/Y')])
                    ->action(function (Chronogramme $record, array $data) {
                        $record->replicate(['statut', 'sortie_id'])->fill(['date_prevue' => $data['date_prevue'], 'statut' => 'planifiee'])->save();
                        Notification::make()->title('Sortie dupliquée')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('annuler')
                        ->label('Annuler la sélection')->icon('heroicon-o-x-circle')->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(
                            fn (Chronogramme $r) => $r->statut === 'realisee' ?: $r->update(['statut' => 'annulee'])
                        ))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
