<?php

namespace App\Filament\Resources\Ravitaillements\Tables;

use App\Filament\Resources\Ravitaillements\Schemas\RavitaillementForm;
use App\Models\Ravitaillement;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class RavitaillementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_ravitaillement', 'desc')
            ->columns([
                TextColumn::make('date_ravitaillement')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->searchable(),
                TextColumn::make('driver.nom_complet')->label('Chauffeur')->searchable()->placeholder('—'),
                TextColumn::make('km_compteur')->label('Kilométrage')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('litres')->suffix(' L')->numeric(1)->summarize(Sum::make()->suffix(' L')->label('Total')),
                TextColumn::make('prix_unitaire')->label('Prix unitaire')->money('XOF'),
                TextColumn::make('montant_total')->label('Montant')->money('XOF')
                    ->state(fn (Ravitaillement $r) => $r->montant_total),
                TextColumn::make('station')->placeholder('—')->toggleable(),
                TextColumn::make('numero_facture')->label('N° facture')->placeholder('—')->toggleable(),
                IconColumn::make('facture_path')->label('Facture')->boolean()
                    ->state(fn (Ravitaillement $r) => (bool) $r->facture_path)->toggleable(),
                TextColumn::make('anomalie')->label('Anomalie')->badge()->color('danger')->placeholder('—')
                    ->formatStateUsing(fn ($state) => RavitaillementForm::ANOMALIES[$state] ?? $state),
                IconColumn::make('valide_at')->label('Validé')->boolean()->state(fn (Ravitaillement $r) => $r->valide_at !== null),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                SelectFilter::make('driver_id')->label('Chauffeur')->relationship('driver', 'nom_complet'),
                SelectFilter::make('anomalie')->options(RavitaillementForm::ANOMALIES),
                TernaryFilter::make('valide')->label('Validation')->placeholder('Tous')->trueLabel('Validés')->falseLabel('À valider')
                    ->queries(true: fn (Builder $q) => $q->whereNotNull('valide_at'), false: fn (Builder $q) => $q->whereNull('valide_at'), blank: fn (Builder $q) => $q),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_ravitaillement', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_ravitaillement', '<=', $v))),
            ])
            ->recordActions([
                Action::make('voir_facture')->label('Voir la facture')->icon('heroicon-o-photo')
                    ->visible(fn (Ravitaillement $r) => $r->facture_path && preg_match('/\.(jpe?g|png|webp)$/i', $r->facture_path) && Storage::disk('local')->exists($r->facture_path))
                    ->modalHeading(fn (Ravitaillement $r) => 'Facture · '.($r->litres + 0).' L'.($r->station ? ' · '.$r->station : ''))
                    ->modalContent(fn (Ravitaillement $r) => view('filament.modals.facture-image', [
                        'src' => 'data:'.Storage::disk('local')->mimeType($r->facture_path).';base64,'.base64_encode(Storage::disk('local')->get($r->facture_path)),
                    ]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
                Action::make('facture')->label('Télécharger la facture')->icon('heroicon-o-paper-clip')
                    ->visible(fn (Ravitaillement $r) => $r->facture_path && Storage::disk('local')->exists($r->facture_path))
                    ->action(fn (Ravitaillement $r) => Storage::disk('local')->download($r->facture_path)),
                Action::make('valider')->label('Valider')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Ravitaillement $r) => $r->valide_at === null && auth()->user()->can('validate_ravitaillements'))
                    ->action(function (Ravitaillement $r) {
                        $r->update(['valide_at' => now(), 'valide_par' => auth()->id()]);
                        Notification::make()->title('Ravitaillement validé')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('valider_masse')->label('Valider la sélection')->icon('heroicon-o-check-badge')
                        ->visible(fn () => auth()->user()->can('validate_ravitaillements'))
                        ->action(fn (Collection $records) => $records->whereNull('valide_at')
                            ->each(fn (Ravitaillement $r) => $r->update(['valide_at' => now(), 'valide_par' => auth()->id()])))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
