<?php

namespace App\Filament\Resources\Espcs\Schemas;

use App\Filament\Support\FreeSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
        ]);
    }
}
