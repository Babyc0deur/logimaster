<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\Espc;
use App\Models\Expense;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Type de frais')->options(Expense::TYPES)->required()->live(),
            Select::make('espc_id')->label('Centre de santé (ESPC)')
                ->options(fn () => Espc::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id'))
                ->searchable()->live()
                ->required(fn (Get $get) => $get('type') === 'enlevement_espc')
                ->visible(fn (Get $get) => $get('type') === 'enlevement_espc')
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    // le bénéficiaire est, par défaut, le centre de santé qui vient enlever
                    if ($state && blank($get('beneficiaire'))) {
                        $set('beneficiaire', Espc::find($state)?->nom);
                    }
                })
                ->helperText("Indemnité de déplacement versée à l'ESPC qui vient enlever ses produits au district : elle est comptée dans le coût global."),
            DatePicker::make('date_depense')->label('Date')->native(false)->displayFormat('d/m/Y')->default(now())->required(),
            TextInput::make('montant')->numeric()->required()->minValue(0)->suffix('FCFA'),
            TextInput::make('beneficiaire')->label('Bénéficiaire')->maxLength(120),
            Select::make('sortie_id')->label('Sortie liée (facultatif)')->relationship('sortie', 'id')->searchable(),
            Select::make('vehicle_id')->label('Véhicule (facultatif)')->relationship('vehicle', 'immatriculation')->searchable()->preload(),
            Textarea::make('commentaire')->columnSpanFull(),
        ]);
    }
}
