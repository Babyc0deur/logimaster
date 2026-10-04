<?php

namespace App\Filament\Resources\Livraisons\Widgets;

use App\Models\LivraisonEspc;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** ESPC non livrés et raisons (derniers 60 jours). */
class NonLivrees extends TableWidget
{
    protected static ?string $heading = 'ESPC non livrés et raisons';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LivraisonEspc::query()->with(['chronogramme.circuit:id,nom', 'espc:id,nom'])
                    ->where('statut', 'non_livre')
                    ->whereHas('chronogramme', fn ($q) => $q->where('district_id', Filament::getTenant()->getKey())->where('statut', '!=', 'annulee')->whereDate('date_prevue', '>=', now()->subDays(60)))
                    ->latest('updated_at')->limit(10)
            )
            ->paginated(false)
            ->emptyStateHeading('Aucun site non livré sur les 60 derniers jours')
            ->columns([
                TextColumn::make('espc.nom')->label('ESPC')->weight('bold'),
                TextColumn::make('chronogramme.circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('chronogramme.date_prevue')->label('Prévue le')->date('d/m/Y'),
                TextColumn::make('raison_non_livraison')->label('Raison')->placeholder('Non renseignée')->wrap(),
            ]);
    }
}
