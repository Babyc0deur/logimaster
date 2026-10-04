<?php

namespace App\Filament\Resources\Chronogrammes;

use App\Filament\Resources\Chronogrammes\Pages\CreateChronogramme;
use App\Filament\Resources\Chronogrammes\Pages\EditChronogramme;
use App\Filament\Resources\Chronogrammes\Pages\ListChronogrammes;
use App\Filament\Resources\Chronogrammes\Schemas\ChronogrammeForm;
use App\Filament\Resources\Chronogrammes\Tables\ChronogrammesTable;
use App\Models\Chronogramme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChronogrammeResource extends Resource
{
    protected static ?string $model = Chronogramme::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Chronogramme';

    protected static ?string $modelLabel = 'sortie planifiée';

    protected static ?string $pluralModelLabel = 'chronogramme';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ChronogrammeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChronogrammesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChronogrammes::route('/'),
            'create' => CreateChronogramme::route('/create'),
            'edit' => EditChronogramme::route('/{record}/edit'),
        ];
    }
}
