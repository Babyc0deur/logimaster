<?php

namespace App\Filament\Resources\Espcs;

use App\Filament\Resources\Espcs\Pages\CreateEspc;
use App\Filament\Resources\Espcs\Pages\EditEspc;
use App\Filament\Resources\Espcs\Pages\ListEspcs;
use App\Filament\Resources\Espcs\Pages\ViewEspc;
use App\Filament\Resources\Espcs\RelationManagers\LivraisonsRelationManager;
use App\Filament\Resources\Espcs\Schemas\EspcForm;
use App\Filament\Resources\Espcs\Schemas\EspcInfolist;
use App\Filament\Resources\Espcs\Tables\EspcsTable;
use App\Models\Espc;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EspcResource extends Resource
{
    /** Accessible depuis le menu utilisateur (en haut), au-dessus de « Déconnexion ». */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $model = Espc::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Centres de santé (ESPC)';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'ESPC';

    protected static ?string $pluralModelLabel = 'Centres de santé (ESPC)';

    public static function form(Schema $schema): Schema
    {
        return EspcForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EspcInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EspcsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [LivraisonsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEspcs::route('/'),
            'create' => CreateEspc::route('/create'),
            'view' => ViewEspc::route('/{record}'),
            'edit' => EditEspc::route('/{record}/edit'),
        ];
    }
}
