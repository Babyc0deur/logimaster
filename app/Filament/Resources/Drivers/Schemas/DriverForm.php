<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Models\Driver;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverForm
{
    public const CATEGORIES = ['A' => 'A (moto)', 'B' => 'B (véhicule léger)', 'C' => 'C (poids lourd)', 'D' => 'D (transport en commun)', 'E' => 'E (remorque)'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Identité')->columns(2)->schema([
                TextInput::make('matricule')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('nom_complet')->label('Nom complet')->required()->maxLength(120),
                TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                TextInput::make('email')->email()->maxLength(160),
            ]),
            Section::make('Affectation')->columns(2)->schema([
                Select::make('vehicule_principal_id')->label('Véhicule principal')
                    ->relationship('vehiculePrincipal', 'immatriculation', modifyQueryUsing: fn ($query) => $query->where('district_id', Filament::getTenant()?->getKey()))
                    ->searchable()->preload(),
                Select::make('statut')->options(Driver::STATUTS)->default('actif')->required(),
            ]),
            Section::make('Permis de conduire')->columns(2)->schema([
                TextInput::make('numero_permis')->label('Numéro de permis')->maxLength(40),
                CheckboxList::make('categories')->label('Catégories')->options(self::CATEGORIES)->columns(2)
                    ->afterStateHydrated(fn (CheckboxList $c, $record) => $c->state(array_filter(array_map('trim', explode(',', (string) $record?->categorie_permis)))))
                    ->dehydrated(false),
                DatePicker::make('date_obtention_permis')->label("Date d'obtention")->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                DatePicker::make('permis_expiration')->label("Date d'expiration")->native(false)->displayFormat('d/m/Y')
                    ->afterOrEqual('date_obtention_permis')
                    ->helperText("Une alerte est émise 30, 15 et 7 jours avant l'expiration."),
            ]),
        ]);
    }

    /** Les cases « Catégories » sont stockées en « B,C » dans categorie_permis. */
    public static function mergeCategories(array $data, array $formState): array
    {
        $data['categorie_permis'] = implode(',', $formState['categories'] ?? []) ?: null;

        return $data;
    }
}
