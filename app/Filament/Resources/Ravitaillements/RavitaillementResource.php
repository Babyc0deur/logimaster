<?php
namespace App\Filament\Resources\Ravitaillements;
use App\Filament\Resources\Ravitaillements\Pages\CreateRavitaillement;
use App\Filament\Resources\Ravitaillements\Pages\EditRavitaillement;
use App\Filament\Resources\Ravitaillements\Pages\ListRavitaillements;
use App\Filament\Resources\Ravitaillements\Schemas\RavitaillementForm;
use App\Filament\Resources\Ravitaillements\Tables\RavitaillementsTable;
use App\Models\Ravitaillement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RavitaillementResource extends Resource
{
    protected static ?string $model = Ravitaillement::class;
    protected static ?string $tenantOwnershipRelationshipName = 'district';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;
    protected static ?string $navigationLabel = 'Ravitaillements';
    protected static string|\UnitEnum|null $navigationGroup = 'Carburant';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'ravitaillement';
    protected static ?string $pluralModelLabel = 'Ravitaillements';

    public static function form(Schema $schema): Schema
    {
        return RavitaillementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RavitaillementsTable::configure($table);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListRavitaillements::route('/'),
            'create' => CreateRavitaillement::route('/create'),
            'edit'   => EditRavitaillement::route('/{record}/edit'),
        ];
    }
}