<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Mot de Passe')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->helperText('Laissez vide pour conserver le mot de passe actuel'),

                Select::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),

                Select::make('region_id')
                    ->label('Région')
                    ->relationship('region', 'name')
                    ->searchable()->preload()
                    ->helperText('Responsable régional : accède automatiquement à tous les districts de cette région.'),

                Select::make('districts')
                    ->label('Districts')
                    ->relationship('districts', 'name')
                    ->multiple()
                    ->preload()
                    ->helperText('Districts accessibles par cet utilisateur'),

                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }
}
