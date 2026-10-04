<?php

namespace App\Filament\Resources\Circuits;

use App\Filament\Resources\Circuits\Pages\CreateCircuit;
use App\Filament\Resources\Circuits\Pages\EditCircuit;
use App\Filament\Resources\Circuits\Pages\ListCircuits;
use App\Filament\Resources\Circuits\Pages\ViewCircuit;
use App\Filament\Resources\Circuits\Schemas\CircuitForm;
use App\Filament\Resources\Circuits\Schemas\CircuitInfolist;
use App\Filament\Resources\Circuits\Tables\CircuitsTable;
use App\Models\Circuit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CircuitResource extends Resource
{
    protected static ?string $model = Circuit::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Circuits';

    protected static ?string $modelLabel = 'circuit';

    protected static ?string $pluralModelLabel = 'Circuits';

    public static function form(Schema $schema): Schema
    {
        return CircuitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CircuitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CircuitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCircuits::route('/'),
            'create' => CreateCircuit::route('/create'),
            'view' => ViewCircuit::route('/{record}'),
            'edit' => EditCircuit::route('/{record}/edit'),
        ];
    }
}
