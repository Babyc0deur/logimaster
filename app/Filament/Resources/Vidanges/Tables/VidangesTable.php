<?php

namespace App\Filament\Resources\Vidanges\Tables;

use App\Domain\Fleet\AlertLevel;
use App\Filament\Resources\Vidanges\Schemas\VidangeForm;
use App\Models\Vidange;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class VidangesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('d/m/Y')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->searchable(),
                TextColumn::make('km')->label('Kilométrage')->numeric(thousandsSeparator: ' ')->suffix(' km')->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => VidangeForm::TYPES[$state] ?? $state),
                TextColumn::make('montant')->money('XOF')->summarize(Sum::make()->money('XOF')->label('Total')),
                TextColumn::make('prestataire')->placeholder('—')->toggleable(),
                TextColumn::make('numero_facture')->label('N° facture')->placeholder('—')->toggleable(),
                TextColumn::make('prochain_km')->label('Prochaine vidange')->numeric(thousandsSeparator: ' ')->suffix(' km')->placeholder('—')
                    ->description(fn (Vidange $r) => $r->prochaine_date ? 'Vers le '.$r->prochaine_date->format('d/m/Y') : null)
                    ->badge()
                    ->color(fn (Vidange $r) => AlertLevel::forKm($r->prochain_km !== null ? $r->prochain_km - ($r->vehicle?->km_actuel ?? 0) : null)->color()),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                SelectFilter::make('type')->options(VidangeForm::TYPES),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))),
            ])
            ->recordActions([
                Action::make('facture')->label('Facture')->icon('heroicon-o-paper-clip')
                    ->visible(fn (Vidange $r) => $r->facture_path && Storage::disk('local')->exists($r->facture_path))
                    ->action(fn (Vidange $r) => Storage::disk('local')->download($r->facture_path)),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
