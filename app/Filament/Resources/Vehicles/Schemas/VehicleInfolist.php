<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\FuelPrice;
use App\Models\Vehicle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informations véhicule')->columns(4)->schema([
                TextEntry::make('immatriculation')->label('Immatriculation')->weight('bold'),
                TextEntry::make('marque')->placeholder('—'),
                TextEntry::make('modele')->label('Modèle')->placeholder('—'),
                TextEntry::make('type_vehicule')->label('Type')->badge()->placeholder('—'),
                TextEntry::make('type_carburant')->label('Carburant')->formatStateUsing(fn ($state) => FuelPrice::TYPES[$state] ?? $state),
                TextEntry::make('prix_carburant')->label('Prix carburant')->suffix(' FCFA/L')->numeric()->placeholder('—'),
                TextEntry::make('consommation_theorique')->label('Consommation théorique')->suffix(' L/100km')->placeholder('—'),
                TextEntry::make('annee_circulation')->label('Mise en circulation')->placeholder('—'),
                TextEntry::make('poids_vide')->label('Poids à vide')->suffix(' kg')->numeric()->placeholder('—'),
                TextEntry::make('capacite_charge')->label('Capacité de charge')->suffix(' kg')->numeric()->placeholder('—'),
                TextEntry::make('volume_utile')->label('Volume utile')->suffix(' m³')->placeholder('—'),
                TextEntry::make('statut')->badge(),
            ]),
            Section::make('Affectation')->columns(4)->schema([
                TextEntry::make('district.name')->label('District'),
                TextEntry::make('appartenance')->formatStateUsing(fn ($state) => Vehicle::APPARTENANCES[$state] ?? $state),
                TextEntry::make('bailleur')->placeholder('—'),
                TextEntry::make('date_reception')->label('Date de réception')->date('d/m/Y')->placeholder('—'),
            ]),
            Section::make('Kilométrage')->columns(4)->schema([
                TextEntry::make('km_actuel')->label('Kilométrage actuel')->numeric(thousandsSeparator: ' ')->suffix(' km'),
                TextEntry::make('date_dernier_releve')->label('Dernier relevé')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('km_vidange')->label('Kilométrage vidange')->numeric(thousandsSeparator: ' ')->suffix(' km')->placeholder('—'),
                TextEntry::make('date_ct')->label('Contrôle technique')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('date_assurance')->label('Assurance')->date('d/m/Y')->placeholder('—'),
            ]),
        ]);
    }
}
