<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Models\Driver;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Fiche chauffeur : identité, affectation, permis. */
class DriverInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Chauffeur')->columns(4)->schema([
                TextEntry::make('nom_complet')->label('Nom')->weight('bold'),
                TextEntry::make('matricule'),
                TextEntry::make('telephone')->label('Téléphone')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
            ]),
            Section::make('Affectation')->columns(4)->schema([
                TextEntry::make('district.name')->label('District'),
                TextEntry::make('vehiculePrincipal.immatriculation')->label('Véhicule principal')->placeholder('—'),
                TextEntry::make('statut')->badge()->formatStateUsing(fn ($state) => Driver::STATUTS[$state] ?? $state),
            ]),
            Section::make('Permis de conduire')->columns(4)->schema([
                TextEntry::make('numero_permis')->label('Numéro')->placeholder('—'),
                TextEntry::make('categorie_permis')->label('Catégories')->badge()->separator(',')->placeholder('—'),
                TextEntry::make('date_obtention_permis')->label("Date d'obtention")->date('d/m/Y')->placeholder('—'),
                TextEntry::make('permis_expiration')->label("Date d'expiration")->date('d/m/Y')->placeholder('—')
                    ->color(fn (Driver $record) => $record->permis_expiration?->isPast() ? 'danger' : null),
            ]),
        ]);
    }
}
