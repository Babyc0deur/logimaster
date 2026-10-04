<?php

namespace App\Filament\Resources\Espcs\Schemas;

use App\Models\Espc;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Fiche ESPC : identité, circuits de rattachement, localisation (carte), contact. L'historique des livraisons est en dessous. */
class EspcInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Établissement')->columns(4)->schema([
                TextEntry::make('nom')->weight('bold'),
                TextEntry::make('type')->badge()->placeholder('—')->formatStateUsing(fn ($state) => EspcForm::TYPES[$state] ?? $state),
                TextEntry::make('district.name')->label('District'),
                TextEntry::make('statut')->badge()->color(fn ($state) => $state === 'actif' ? 'success' : 'gray'),
                TextEntry::make('circuits')->label('Circuit(s) de rattachement')->state(fn (Espc $e) => $e->circuits()->pluck('circuits.nom')->all())->badge()->separator(',')->placeholder('Aucun')->columnSpanFull(),
            ]),
            Section::make('Localisation')->columns(3)->schema([
                TextEntry::make('adresse')->placeholder('—')->columnSpanFull(),
                TextEntry::make('gps_lat')->label('Latitude')->placeholder('—'),
                TextEntry::make('gps_lon')->label('Longitude')->placeholder('—'),
                ViewEntry::make('carte')->label('')->columnSpanFull()->view('filament.infolists.espc-map'),
            ]),
            Section::make('Contact')->columns(3)->schema([
                TextEntry::make('responsable')->placeholder('—'),
                TextEntry::make('telephone')->label('Téléphone')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
            ]),
        ]);
    }
}
