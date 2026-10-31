<?php

namespace App\Filament\Resources\Espcs\Schemas;

use App\Filament\Support\FreeSelect;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use App\Models\Espc;
use Filament\Schemas\Schema;

class EspcForm
{
    public const TYPES = [
        'CSR' => 'CSR — Centre de santé rural', 'CSU' => 'CSU — Centre de santé urbain', 'maternite' => 'Maternité', 'dispensaire' => 'Dispensaire',
        'hopital' => 'Hôpital', 'centre_medical' => 'Centre médical', 'PMI' => 'PMI', 'autre' => 'Autre site',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Établissement')->columns(2)->schema([
                TextInput::make('nom')->required()->maxLength(160),
                FreeSelect::make('type', 'espc', self::TYPES, 'Type'),
                Select::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif'])->default('actif')->required(),
            ]),
            Section::make('Localisation')->columns(3)->schema([
                TextInput::make('adresse')->maxLength(200)->columnSpanFull(),
                TextInput::make('gps_lat')->label('Latitude')->numeric()->minValue(-90)->maxValue(90)->helperText('Ex. 7.0611'),
                TextInput::make('gps_lon')->label('Longitude')->numeric()->minValue(-180)->maxValue(180)->helperText('Ex. -4.5042'),
            ]),
            Section::make('Desserte')->columns(3)->schema([
                TextInput::make('distance_aller_retour_km')->label('Distance aller-retour depuis le district')->numeric()->minValue(0)->suffix('km'),
                TextInput::make('colis_mois')->label('Colis par mois (estimé)')->numeric()->minValue(0)->integer(),
                TextInput::make('poids_mois_kg')->label('Poids par mois (estimé)')->numeric()->minValue(0)->suffix('kg'),
                Select::make('type_voie')->label('Type de voie')->options(Espc::TYPES_VOIE),
                Select::make('etat_voie')->label('État de la voie')->options(Espc::ETATS_VOIE),
                Select::make('etat_voie_pluie')->label('État en saison pluvieuse')->options(Espc::ETATS_VOIE),
                CheckboxList::make('mois_mauvais')->label('Mois où la voie est en mauvais état')->options(Espc::MOIS)->columns(6)->columnSpanFull(),
                Toggle::make('point_transit')->label('Point de transit')->helperText('Un point de transit livre d\'autres sites du circuit.'),
            ]),
        ]);
    }
}
