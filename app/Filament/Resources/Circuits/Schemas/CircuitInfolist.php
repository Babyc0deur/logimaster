<?php

namespace App\Filament\Resources\Circuits\Schemas;

use App\Models\Circuit;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Fiche circuit détaillée : caractéristiques, étapes avec distances, carte interactive, ESPC desservies. */
class CircuitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Circuit')->columns(4)->schema([
                TextEntry::make('nom')->weight('bold'),
                TextEntry::make('district.name')->label('District'),
                TextEntry::make('statut')->badge()->color(fn ($state) => $state === 'actif' ? 'success' : 'gray'),
                TextEntry::make('frequence')->label('Fréquence')->formatStateUsing(fn ($state) => CircuitForm::FREQUENCES[$state] ?? $state)->placeholder('—'),
                TextEntry::make('saison')->label('Saison')->badge()
                    ->formatStateUsing(fn ($state) => Circuit::SAISONS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'seche' => 'warning', 'pluvieuse' => 'info', default => 'gray' }),
                TextEntry::make('version')->label('Version')->badge()->formatStateUsing(fn ($state) => Circuit::VERSIONS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'optimise' ? 'success' : 'gray'),
                TextEntry::make('urgence')->label('Urgence')->badge()->formatStateUsing(fn ($state) => $state ? 'Circuit d\'urgence' : 'Non')->color(fn ($state) => $state ? 'danger' : 'gray'),
                TextEntry::make('distance_totale')->label('Distance totale')->suffix(' km')->placeholder('—'),
                TextEntry::make('temps')->label('Temps estimé')->state(fn (Circuit $c) => $c->temps_estime_min ? sprintf('%dh%02d', intdiv($c->temps_estime_min, 60), $c->temps_estime_min % 60).($c->depasseDureeMax() ? ' ⚠ plus de 8 h' : '') : null)->placeholder('—')
                    ->color(fn (Circuit $c) => $c->depasseDureeMax() ? 'danger' : null),
                TextEntry::make('decomposition')->label('Aller / retour / dépôt / coaching')
                    ->state(fn (Circuit $c) => collect([$c->temps_aller_min, $c->temps_retour_min, $c->temps_depot_min, $c->temps_coaching_min])->every(fn ($v) => $v === null)
                        ? null : collect([$c->temps_aller_min, $c->temps_retour_min, $c->temps_depot_min, $c->temps_coaching_min])->map(fn ($v) => $v === null ? '—' : $v.' min')->join(' · '))
                    ->placeholder('—'),
                TextEntry::make('nb_espc')->label("Nombre d'ESPC")->state(fn (Circuit $c) => $c->espc()->count()),
                TextEntry::make('point_depart')->label('Départ')->placeholder('—'),
            ]),
            Section::make('Étapes')->schema([ViewEntry::make('etapes')->label('')->view('filament.infolists.circuit-etapes')]),
            Section::make('Carte')->schema([ViewEntry::make('carte')->label('')->view('filament.infolists.circuit-map')]),
        ]);
    }
}
