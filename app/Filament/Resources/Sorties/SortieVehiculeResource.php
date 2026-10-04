<?php

namespace App\Filament\Resources\Sorties;

use App\Filament\Resources\Sorties\Pages\CreateSortie;
use App\Filament\Resources\Sorties\Pages\EditSortie;
use App\Filament\Resources\Sorties\Pages\ListSorties;
use App\Filament\Resources\Sorties\Schemas\SortieForm;
use App\Filament\Resources\Sorties\Tables\SortiesTable;
use App\Models\SortieVehicule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SortieVehiculeResource extends Resource
{
    protected static ?string $model = SortieVehicule::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Sorties Véhicules';

    protected static ?string $modelLabel = 'sortie';

    protected static ?string $pluralModelLabel = 'sorties';

    public static function form(Schema $schema): Schema
    {
        return SortieForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SortiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSorties::route('/'),
            'create' => CreateSortie::route('/create'),
            'edit'   => EditSortie::route('/{record}/edit'),
        ];
    }
}
