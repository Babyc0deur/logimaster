<?php

namespace App\Filament\Resources\Districts;

use App\Filament\Resources\Districts\Pages\ManageDistricts;
use App\Models\District;
use App\Models\Region;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Organisation : districts sanitaires et leur région (périmètre de l'utilisateur). Création/édition : manage_districts. */
class DistrictResource extends Resource
{
    /** Accessible depuis le menu utilisateur (en haut), au-dessus de « Déconnexion ». */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $model = District::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Districts';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'district';

    protected static ?string $pluralModelLabel = 'districts';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_districts');
    }

    /** Création réservée au national : un district appartient à une région, donc à l'organisation. */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) $user?->can('manage_districts') && $user->accessibleDistrictIds() === null;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) $user?->can('manage_districts') && $user->canAccessDistrict($record->getKey());
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = auth()->user()?->accessibleDistrictIds();

        return parent::getEloquentQuery()->with('region.pres')->withCount(['users', 'vehicles', 'drivers'])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom du district')->required()->maxLength(120),
            Select::make('region_id')->label('Région')->required()->searchable()->options(fn () => Region::orderBy('name')->pluck('name', 'id')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('numero')->label('N°')->rowIndex()->alignCenter()->color('gray')->width('3rem'),
                TextColumn::make('name')->label('District')->searchable()->sortable()->weight('bold'),
                TextColumn::make('region.name')->label('Région')->sortable(),
                TextColumn::make('region.pres.name')->label('PRES')->toggleable(),
                TextColumn::make('users_count')->label('Utilisateurs')->numeric(),
                TextColumn::make('vehicles_count')->label('Véhicules')->numeric(),
                TextColumn::make('drivers_count')->label('Chauffeurs')->numeric(),
            ])
            ->filters([
                SelectFilter::make('region_id')->label('Région')
                    ->options(fn () => Region::whereIn('id', static::getEloquentQuery()->reorder()->select('region_id'))->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDistricts::route('/')];
    }

    /** Un district créé reçoit un identifiant technique et un secret aléatoires (la synchronisation hors ligne n'est pas utilisée). */
    public static function technicalFields(): array
    {
        return ['sync_id' => Str::upper(Str::random(12)), 'sync_password_hash' => bcrypt(Str::random(32))];
    }
}
