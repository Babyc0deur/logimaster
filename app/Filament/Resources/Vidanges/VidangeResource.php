<?php
namespace App\Filament\Resources\Vidanges;
use App\Filament\Resources\Vidanges\Pages\CreateVidange;
use App\Filament\Resources\Vidanges\Pages\EditVidange;
use App\Filament\Resources\Vidanges\Pages\ListVidanges;
use App\Filament\Resources\Vidanges\Schemas\VidangeForm;
use App\Filament\Resources\Vidanges\Tables\VidangesTable;
use App\Models\Vidange;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VidangeResource extends Resource
{
    protected static ?string $model = Vidange::class;
    protected static ?string $tenantOwnershipRelationshipName = 'district';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;
    protected static ?string $navigationLabel = 'Vidanges';
    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'vidange';
    protected static ?string $pluralModelLabel = 'Vidanges';

    public static function form(Schema $schema): Schema
    {
        return VidangeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VidangesTable::configure($table);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListVidanges::route('/'),
            'create' => CreateVidange::route('/create'),
            'edit'   => EditVidange::route('/{record}/edit'),
        ];
    }
}