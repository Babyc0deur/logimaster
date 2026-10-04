<?php

namespace App\Filament\Resources\Sorties\Tables;

use App\Models\SortieVehicule;
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
use Illuminate\Support\Facades\DB;

class SortiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_sortie', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum(['ravitaillements as carburant_sum' => fn ($q) => $q], DB::raw('litres * prix_unitaire'))
                ->withSum('expenses as autres_sum', 'montant'))
            ->columns([
                TextColumn::make('date_sortie')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->searchable(),
                TextColumn::make('driver.nom_complet')->label('Chauffeur')->searchable()->placeholder('—'),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => SortieVehicule::MOTIFS[$state] ?? $state),
                TextColumn::make('distance')->label('Distance')->suffix(' km')->placeholder('—')
                    ->state(fn (SortieVehicule $r) => $r->distance),
                TextColumn::make('carburant_sum')->label('Coût carburant')->money('XOF')->placeholder('0'),
                TextColumn::make('cout_total')->label('Coût total')->money('XOF')
                    ->state(fn (SortieVehicule $r) => (float) $r->carburant_sum + (float) $r->autres_sum),
                TextColumn::make('statut')->badge()
                    ->formatStateUsing(fn ($state) => SortieVehicule::STATUTS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'planifiee' => 'info', 'en_cours' => 'warning', 'terminee' => 'primary', 'validee' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->options(SortieVehicule::STATUTS)->multiple(),
                SelectFilter::make('motif')->options(SortieVehicule::MOTIFS),
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                SelectFilter::make('driver_id')->label('Chauffeur')->relationship('driver', 'nom_complet'),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_sortie', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_sortie', '<=', $v))),
            ])
            ->recordActions([
                Action::make('valider')->label('Valider')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SortieVehicule $r) => $r->statut === 'terminee' && auth()->user()->can('validate_sorties'))
                    ->requiresConfirmation()
                    ->action(function (SortieVehicule $r) {
                        self::validate($r);
                        Notification::make()->title('Sortie validée')->success()->send();
                    }),
                Action::make('dupliquer')->label('Dupliquer')->icon('heroicon-o-document-duplicate')
                    ->visible(fn () => auth()->user()->can('create_sorties'))
                    ->schema([DatePicker::make('date_sortie')->label('Nouvelle date')->default(now())->required()->native(false)->displayFormat('d/m/Y')])
                    ->action(function (SortieVehicule $r, array $data) {
                        $copy = $r->replicate(['km_arrivee', 'statut', 'validated_at', 'validated_by', 'circuit_respecte', 'version'])
                            ->fill(['date_sortie' => $data['date_sortie'], 'statut' => 'planifiee', 'km_depart' => $r->vehicle->km_actuel ?? $r->km_depart]);
                        $copy->save();
                        $copy->passagers()->sync($r->passagers->pluck('id'));
                        Notification::make()->title('Sortie dupliquée (planifiée)')->success()->send();
                    }),
                EditAction::make()->visible(fn (SortieVehicule $r) => auth()->user()->can('update_sorties') && $r->statut !== 'validee'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('valider_masse')->label('Valider la sélection')->icon('heroicon-o-check-badge')->color('success')
                        ->visible(fn () => auth()->user()->can('validate_sorties'))
                        ->requiresConfirmation()
                        ->modalDescription('Seules les sorties « Terminée » seront validées.')
                        ->action(function (Collection $records) {
                            $n = $records->where('statut', 'terminee')->each(fn (SortieVehicule $r) => self::validate($r))->count();
                            Notification::make()->title("{$n} sortie(s) validée(s)")->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()->visible(fn () => auth()->user()->can('delete_sorties')),
                ]),
            ]);
    }

    private static function validate(SortieVehicule $sortie): void
    {
        $sortie->update(['statut' => 'validee', 'validated_at' => now(), 'validated_by' => auth()->id()]);
    }
}
