<?php

namespace App\Filament\Resources\Immobilisations\Widgets;

use App\Models\Immobilisation;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Véhicules les plus problématiques : nombre d'immobilisations, jours et coût cumulés. */
class ProblematicVehicles extends TableWidget
{
    protected static ?string $heading = 'Véhicules les plus problématiques';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Vehicle::query()
                    ->where('district_id', Filament::getTenant()->getKey())
                    ->withCount('immobilisations')
                    ->withSum('immobilisations as cout_total', 'montant')
                    ->has('immobilisations')
                    ->orderByDesc('immobilisations_count')->orderByDesc('cout_total')
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('immatriculation')->label('Véhicule')->weight('bold'),
                TextColumn::make('immobilisations_count')->label('Immobilisations'),
                TextColumn::make('jours')->label('Jours immobilisé')->suffix(' j')
                    ->state(fn (Vehicle $v) => Immobilisation::where('vehicle_id', $v->id)->get()->sum('duree_jours')),
                TextColumn::make('cout_total')->label('Coût cumulé')->money('XOF')->placeholder('—'),
            ]);
    }
}
