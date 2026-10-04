<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom du Rôle')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                    
                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    ->columns(3)
                    ->gridDirection('row')
                    ->bulkToggleable(),
            ]);
    }
}
