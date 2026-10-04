<?php
namespace App\Filament\Resources\Immobilisations;
use App\Filament\Resources\Immobilisations\Pages\CreateImmobilisation;
use App\Filament\Resources\Immobilisations\Pages\EditImmobilisation;
use App\Filament\Resources\Immobilisations\Pages\ListImmobilisations;
use App\Filament\Resources\Immobilisations\Schemas\ImmobilisationForm;
use App\Filament\Resources\Immobilisations\Tables\ImmobilisationsTable;
use App\Models\Immobilisation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImmobilisationResource extends Resource
{
    protected static ?string $model = Immobilisation::class;
    protected static ?string $tenantOwnershipRelationshipName = 'district';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;
    protected static ?string $navigationLabel = 'Immobilisations';
    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'immobilisation';
    protected static ?string $pluralModelLabel = 'Immobilisations';

    public static function form(Schema $schema): Schema
    {
        return ImmobilisationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImmobilisationsTable::configure($table);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListImmobilisations::route('/'),
            'create' => CreateImmobilisation::route('/create'),
            'edit'   => EditImmobilisation::route('/{record}/edit'),
        ];
    }
}