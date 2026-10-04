<?php

namespace App\Filament\Resources\FuelPrices;

use App\Filament\Resources\FuelPrices\Pages\ManageFuelPrices;
use App\Models\FuelPrice;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Paramètres carburant : prix du gasoil / de l'essence avec historique (valable à partir de la date d'effet). */
class FuelPriceResource extends Resource
{
    protected static ?string $model = FuelPrice::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Prix du carburant';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'prix du carburant';

    protected static ?string $pluralModelLabel = 'prix du carburant';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type_carburant')->label('Carburant')->options(FuelPrice::TYPES)->required(),
            TextInput::make('prix')->label('Prix')->numeric()->required()->minValue(1)->suffix('FCFA/L'),
            DatePicker::make('date_effet')->label("Applicable à partir du")->required()->default(now())->native(false)->displayFormat('d/m/Y'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_effet', 'desc')
            ->columns([
                TextColumn::make('type_carburant')->label('Carburant')->badge()->formatStateUsing(fn ($state) => FuelPrice::TYPES[$state] ?? $state)->sortable(),
                TextColumn::make('prix')->label('Prix')->suffix(' FCFA/L')->numeric(thousandsSeparator: ' ')->sortable(),
                TextColumn::make('date_effet')->label('Depuis le')->date('d/m/Y')->sortable(),
                TextColumn::make('en_vigueur')->label('')->badge()->color('success')
                    ->state(fn (FuelPrice $r) => FuelPrice::current($r->type_carburant) === (float) $r->prix && $r->date_effet->lte(today()) ? 'En vigueur' : null),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFuelPrices::route('/')];
    }
}
