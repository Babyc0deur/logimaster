<?php

namespace App\Filament\Resources\Chronogrammes\Schemas;

use App\Models\Chronogramme;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ChronogrammeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date_prevue')
                ->label('Date prévue')
                ->native(false)->displayFormat('d/m/Y')
                ->required()->live(),
            TimePicker::make('heure_depart')->label('Heure de départ')->seconds(false),
            Select::make('vehicle_id')
                ->label('Véhicule')
                ->relationship('vehicle', 'immatriculation')
                ->searchable()->preload()->live()
                ->helperText('Facultatif : peut être affecté plus tard, au démarrage de la sortie.')
                ->rules([fn (Get $get, ?Model $record): Closure => self::noDoubleBooking('vehicle_id', $get, $record, 'Ce véhicule est déjà planifié ce jour-là.')]),
            Select::make('driver_id')
                ->label('Chauffeur')
                ->relationship('driver', 'nom_complet')
                ->searchable()->preload()->live()
                ->rules([fn (Get $get, ?Model $record): Closure => self::noDoubleBooking('driver_id', $get, $record, 'Ce chauffeur est déjà planifié ce jour-là.')]),
            Select::make('circuit_id')
                ->label('Circuit')
                ->relationship('circuit', 'nom')
                ->searchable()->preload(),
            Select::make('motif')
                ->options([
                    'distribution' => 'Livraison ESPC',
                    'redistribution' => 'Redistribution',
                    'enlevement_npsp' => 'Enlèvement NPSP',
                    'supervision' => 'Supervision',
                    'coaching' => 'Coaching',
                    'autre' => 'Autres',
                ])
                ->default('distribution')->required(),
            Select::make('personnels')
                ->label('Équipe (chef de mission, passagers)')
                ->relationship('personnels', 'nom_complet', modifyQueryUsing: fn ($query) => $query->where('district_id', \Filament\Facades\Filament::getTenant()?->getKey())->where('statut', 'actif')->orderBy('nom_complet'))
                ->multiple()->searchable()->preload()
                ->helperText('Reçoivent la sortie sur leur téléphone (application mobile) une fois le planning validé par le superviseur.'),
            TextInput::make('destination')->maxLength(160),
            Select::make('statut')
                ->options(Chronogramme::STATUTS)
                ->default('planifiee')->required(),
            Textarea::make('commentaires')->columnSpanFull(),
        ]);
    }

    /** Refuse deux entrées non annulées pour le même véhicule (ou chauffeur) le même jour. */
    private static function noDoubleBooking(string $column, Get $get, ?Model $record, string $message): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($column, $get, $record, $message) {
            $date = $get('date_prevue');
            if (! $value || ! $date) {
                return;
            }
            $conflict = Chronogramme::where($column, $value)->whereDate('date_prevue', $date)
                ->where('statut', '!=', 'annulee')
                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
            if ($conflict && $get('statut') !== 'annulee') {
                $fail($message);
            }
        };
    }
}
