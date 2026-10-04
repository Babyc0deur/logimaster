<?php
namespace App\Filament\Resources\Expenses\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
class ExpensesTable {
    public static function configure(Table $table): Table {
        return $table->defaultSort("date_depense", "desc")->columns([ 
                \Filament\Tables\Columns\TextColumn::make("date_depense")->date("d/m/Y")->label("Date")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("vehicle.immatriculation")->label("Véhicule")->placeholder("—")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("type")->badge(),
                \Filament\Tables\Columns\TextColumn::make("montant")->money("XOF")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("beneficiaire")->searchable(),
         ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')->options(fn () => \App\Models\Expense::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type', 'type')),
                \Filament\Tables\Filters\Filter::make('periode')->label('Période')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()),
                        \Filament\Forms\Components\DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil()),
                    ])
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_depense', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_depense', '<=', $v))),
            ])
            ->recordActions([ EditAction::make() ])
            ->toolbarActions([ BulkActionGroup::make([ DeleteBulkAction::make() ]) ]);
    }
}