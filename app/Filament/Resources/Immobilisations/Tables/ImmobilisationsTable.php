<?php

namespace App\Filament\Resources\Immobilisations\Tables;

use App\Models\Immobilisation;
use Carbon\CarbonImmutable;
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

class ImmobilisationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_debut', 'desc')
            ->columns([
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->searchable(),
                TextColumn::make('periode')->label('Période')
                    ->state(fn (Immobilisation $r) => $r->date_debut->format('d/m/Y').' → '.($r->date_fin?->format('d/m/Y') ?? 'en cours')),
                TextColumn::make('duree_jours')->label('Durée')->suffix(' j')
                    ->state(fn (Immobilisation $r) => $r->duree_jours)
                    ->color(fn (Immobilisation $r) => $r->statut !== 'terminee' && $r->duree_jours > 7 ? 'danger' : null),
                TextColumn::make('motif')->badge()->formatStateUsing(fn ($state) => Immobilisation::MOTIFS[$state] ?? $state),
                TextColumn::make('montant')->label('Coût')->money('XOF')->placeholder('—')->summarize(Sum::make()->money('XOF')->label('Total')),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => Immobilisation::STATUTS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'terminee' => 'success', 'en_attente_pieces' => 'danger', default => 'warning' }),
                TextColumn::make('impact')->label('Impact disponibilité (mois en cours)')
                    ->state(function (Immobilisation $r) {
                        $start = CarbonImmutable::now()->startOfMonth();
                        $end = CarbonImmutable::now()->endOfMonth();
                        $from = $r->date_debut->toImmutable()->startOfDay()->max($start);
                        $to = ($r->date_fin?->toImmutable()->startOfDay() ?? CarbonImmutable::today())->min($end);
                        $days = max(0, (int) $from->diffInDays($to) + 1);
                        $days = $to->lt($from) ? 0 : $days;

                        return $days > 0 ? sprintf('%d j (%.0f %% du mois)', $days, $days / $start->daysInMonth * 100) : '—';
                    }),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                SelectFilter::make('motif')->options(Immobilisation::MOTIFS),
                SelectFilter::make('statut')->options(Immobilisation::STATUTS),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_debut', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_debut', '<=', $v))),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
