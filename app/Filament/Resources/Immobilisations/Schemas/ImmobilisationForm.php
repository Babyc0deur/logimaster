<?php

namespace App\Filament\Resources\Immobilisations\Schemas;

use App\Models\Immobilisation;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ImmobilisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation')->searchable()->preload()->required(),
            Select::make('motif')->options(Immobilisation::MOTIFS)->required(),
            DatePicker::make('date_debut')->label('Début de l\'indisponibilité')->native(false)->displayFormat('d/m/Y')->default(now())->required()->live(),
            DatePicker::make('date_fin')->label('Fin (prévue ou réelle)')->native(false)->displayFormat('d/m/Y')->live()
                ->afterOrEqual('date_debut'),
            Text::make(function (Get $get) {
                if (! $get('date_debut')) {
                    return 'Durée : —';
                }
                $debut = CarbonImmutable::parse($get('date_debut'))->startOfDay();
                $fin = $get('date_fin') ? CarbonImmutable::parse($get('date_fin'))->startOfDay() : today()->toImmutable();
                $jours = max(1, (int) $debut->diffInDays($fin) + 1);

                return "Durée : {$jours} jour(s)".($get('date_fin') ? '' : ' (en cours, jusqu\'à aujourd\'hui)');
            })->columnSpanFull(),
            Select::make('statut')->options(Immobilisation::STATUTS)->default('en_cours')->required(),
            TextInput::make('prestataire')->maxLength(120),
            TextInput::make('montant')->numeric()->minValue(0)->suffix('FCFA'),
            TextInput::make('numero_facture')->label('N° facture')->maxLength(60),
            FileUpload::make('facture_path')->label('Facture')->disk('local')->directory('factures')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable()->openable()->columnSpanFull(),
            Textarea::make('description')->columnSpanFull(),
        ])->columns(2);
    }
}
