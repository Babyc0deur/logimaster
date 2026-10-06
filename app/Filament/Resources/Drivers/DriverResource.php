<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Pages\ViewDriver;
use App\Filament\Resources\Drivers\RelationManagers;
use App\Filament\Resources\Drivers\Schemas\DriverInfolist;
use App\Filament\Resources\Drivers\Schemas\DriverForm;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\Driver;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    /** Les chauffeurs se gèrent dans la liste unique « Personnel » (fonction Chauffeur) ; cette fiche reste accessible depuis la personne. */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Driver::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Chauffeurs';

    protected static ?string $modelLabel = 'chauffeur';

    protected static ?string $pluralModelLabel = 'chauffeurs';

    public static function form(Schema $schema): Schema
    {
        return DriverForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\SortiesRelationManager::class, RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'view'   => ViewDriver::route('/{record}'),
            'edit'   => EditDriver::route('/{record}/edit'),
        ];
    }
}
