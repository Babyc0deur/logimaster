<?php

namespace App\Filament\Resources\Pres;

use App\Filament\Resources\Pres\Pages\ManagePres;
use App\Models\District;
use App\Models\Pres;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Organisation : PRES (pôles régionaux) et leurs régions (menu du profil, en haut à droite). Création et modification : national. */
class PresResource extends Resource
{
    protected static ?string $model = Pres::class;

    protected static bool $isScopedToTenant = false;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'PRES';

    protected static ?string $modelLabel = 'PRES';

    protected static ?string $pluralModelLabel = 'PRES';

    protected static ?string $slug = 'pres';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_districts');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->isNational();
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->isNational();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = auth()->user()?->accessibleDistrictIds();

        return parent::getEloquentQuery()->withCount('regions')
            ->when($ids !== null, fn ($q) => $q->whereHas('regions.districts', fn ($d) => $d->whereIn('districts.id', $ids)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom du PRES')->required()->maxLength(120)->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('numero')->label('N°')->rowIndex()->alignCenter()->color('gray')->width('3rem'),
                TextColumn::make('name')->label('PRES')->searchable()->sortable()->weight('bold'),
                TextColumn::make('regions_count')->label('Régions')->numeric()->sortable(),
                TextColumn::make('districts')->label('Districts')->numeric()
                    ->state(fn (Pres $record) => District::whereIn('region_id', $record->regions()->select('id'))->count()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePres::route('/')];
    }
}
