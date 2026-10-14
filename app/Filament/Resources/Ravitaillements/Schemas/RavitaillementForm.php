<?php

namespace App\Filament\Resources\Ravitaillements\Schemas;

use App\Models\FuelPrice;
use App\Models\Ravitaillement;
use App\Models\Vehicle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RavitaillementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation')
                ->searchable()->preload()->required()->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    $v = Vehicle::find($state);
                    $set('prix_unitaire', $v?->currentFuelPrice());
                    $set('km_compteur', $v?->km_actuel);
                }),
            \Filament\Forms\Components\DatePicker::make('date_ravitaillement')->label('Date')->native(false)->displayFormat('d/m/Y')->default(now())->required()->maxDate(now()),
            Select::make('motif')->label('Motif du déplacement')->options(\App\Models\SortieVehicule::MOTIFS)->helperText('Facultatif : sinon repris de la sortie liée.'),
            Select::make('driver_id')->label('Chauffeur')->relationship('driver', 'nom_complet')->searchable()->preload(),
            Select::make('sortie_id')->label('Sortie liée')->relationship('sortie', 'date_sortie')->searchable()->preload(),
            TextInput::make('km_compteur')->label('Kilométrage compteur')->numeric()->minValue(0)->suffix('km'),
            TextInput::make('litres')->numeric()->required()->minValue(0.1)->suffix('L')->live(onBlur: true),
            TextInput::make('prix_unitaire')->label('Prix unitaire')->numeric()->required()->minValue(1)->suffix('FCFA/L')->live(onBlur: true)
                ->helperText('Prérempli selon le type de carburant du véhicule (Paramètres carburant). Modifiable : une confirmation est demandée.'),
            \Filament\Forms\Components\Toggle::make('prix_confirme')->dehydrated(false)->accepted()
                ->label(fn (Get $get) => 'Je confirme ce prix, différent du prix du véhicule ('.number_format((float) Vehicle::find($get('vehicle_id'))?->currentFuelPrice(), 0, ',', ' ').' FCFA/L)')
                ->visible(fn (Get $get) => ($usual = Vehicle::find($get('vehicle_id'))?->currentFuelPrice()) !== null && filled($get('prix_unitaire')) && abs((float) $get('prix_unitaire') - $usual) > 0.001)
                ->columnSpanFull(),
            Text::make(fn (Get $get) => 'Montant total : '.number_format((float) $get('litres') * (float) $get('prix_unitaire'), 0, ',', ' ').' FCFA')
                ->columnSpanFull(),
            TextInput::make('station')->maxLength(120),
            TextInput::make('numero_facture')->label('N° facture / coupon')->maxLength(60),
            FileUpload::make('facture_path')->label('Facture')->disk('local')->directory('factures')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                ->downloadable()->openable()->previewable()->columnSpanFull(),
        ])->columns(2);
    }

    public const ANOMALIES = ['surconsommation' => 'Surconsommation', 'km_incoherent' => 'Compteur incohérent'];
}
