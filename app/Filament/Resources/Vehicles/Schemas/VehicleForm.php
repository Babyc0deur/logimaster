<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Filament\Support\FreeSelect;
use App\Models\FuelPrice;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class VehicleForm
{
    public const DOCUMENT_CATEGORIES = [
        'carte_grise' => 'Carte grise',
        'assurance' => 'Assurance',
        'vignette' => 'Vignette',
        'controle_technique' => 'Contrôle technique',
        'autre' => 'Autre',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Informations de base')->schema([
                    TextInput::make('immatriculation')
                        ->required()->maxLength(20)
                        ->regex('/^[A-Za-z0-9][A-Za-z0-9 \-]{2,19}$/')
                        ->validationMessages(['regex' => "Format d'immatriculation invalide (lettres, chiffres, espaces et tirets)."])
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim($state))),
                    FreeSelect::make('marque', 'vehicles', ['Toyota' => 'Toyota', 'Ford' => 'Ford', 'Nissan' => 'Nissan', 'Mitsubishi' => 'Mitsubishi', 'Yamaha' => 'Yamaha'], 'Marque'),
                    TextInput::make('modele')->label('Modèle')->maxLength(60),
                    FreeSelect::make('type_vehicule', 'vehicles', ['4x4' => '4x4', 'camion' => 'Camion', 'fourgon' => 'Fourgon', 'moto' => 'Moto', 'minibus' => 'Minibus', 'pickup' => 'Pick-up'], 'Type de véhicule'),
                    TextInput::make('annee_circulation')->label('Année de mise en circulation')
                        ->numeric()->minValue(1980)->maxValue((int) date('Y')),
                ])->columns(2),

                Step::make('Caractéristiques techniques')->schema([
                    Select::make('type_carburant')->label('Type de carburant')
                        ->options(FuelPrice::TYPES)->default('diesel')->required()->live()
                        ->afterStateUpdated(fn (Set $set, $state) => $set('prix_carburant', FuelPrice::current($state))),
                    TextInput::make('prix_carburant')->label('Prix du carburant')->numeric()->suffix('FCFA/L')
                        ->helperText('Rempli automatiquement depuis les paramètres carburant.'),
                    TextInput::make('consommation_theorique')->label('Consommation théorique')->numeric()->suffix('L/100km'),
                    TextInput::make('poids_vide')->label('Poids à vide')->numeric()->suffix('kg'),
                    TextInput::make('capacite_charge')->label('Capacité de charge')->numeric()->suffix('kg'),
                    TextInput::make('volume_utile')->label('Volume utile')->numeric()->suffix('m³'),
                ])->columns(2),

                Step::make('Affectation')->schema([
                    Select::make('appartenance')->options(Vehicle::APPARTENANCES)->default('district')->required(),
                    TextInput::make('bailleur')->maxLength(120),
                    DatePicker::make('date_reception')->label('Date de réception')->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                    Select::make('statut')->options([
                        'disponible' => 'Disponible', 'en_mission' => 'En mission',
                        'en_maintenance' => 'Immobilisé (maintenance)', 'hors_service' => 'Hors service',
                    ])->default('disponible')->required(),
                    TextInput::make('km_actuel')->label('Kilométrage actuel')->numeric()->default(0)->minValue(0)->suffix('km'),
                    DatePicker::make('date_dernier_releve')->label('Dernier relevé')->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                    TextInput::make('km_vidange')->label('Kilométrage prochaine vidange')->numeric()
                        ->gt(fn ($get) => $get('km_actuel') ?: 0)
                        ->helperText('Doit être supérieur au kilométrage actuel.')->suffix('km'),
                ])->columns(2),

                Step::make('Documents')->schema([
                    DatePicker::make('date_ct')->label('Contrôle technique (expiration)')->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('date_assurance')->label('Assurance (expiration)')->native(false)->displayFormat('d/m/Y'),
                    Repeater::make('documents')->label('Documents initiaux')
                        ->relationship('documents')->addActionLabel('Ajouter un document')->columnSpanFull()->defaultItems(0)
                        ->schema([
                            Select::make('categorie')->options(self::DOCUMENT_CATEGORIES)->required(),
                            DatePicker::make('date_expiration')->label("Date d'expiration")->native(false)->displayFormat('d/m/Y'),
                            FileUpload::make('fichier_url')->label('Fichier')->required()
                                ->disk('local')->directory('documents')->visibility('private')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                                ->downloadable()->openable()->columnSpanFull(),
                        ])->columns(2)
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, $livewire) => $data + [
                            'district_id' => $livewire->getRecord()?->district_id ?? \Filament\Facades\Filament::getTenant()?->getKey(),
                            'uploaded_by' => auth()->id(),
                        ]),
                ])->columns(2),
            ])->skippable()->columnSpanFull(),
        ]);
    }
}
