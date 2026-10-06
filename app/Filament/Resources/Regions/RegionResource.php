<?php

namespace App\Filament\Resources\Regions;

use App\Filament\Resources\Regions\Pages\ManageRegions;
use App\Models\Pres;
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

/** Organisation : régions sanitaires et leur PRES (menu du profil, en haut à droite). Création et modification : national. */
class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static bool $isScopedToTenant = false;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Régions';

    protected static ?string $modelLabel = 'région';

    protected static ?string $pluralModelLabel = 'régions';

    protected static ?string $slug = 'regions';

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
        return false;   // une région porte des districts : on la renomme ou on la rattache à un autre PRES, on ne la supprime pas
    }

    public static function getEloquentQuery(): Builder
    {
        $ids = auth()->user()?->accessibleDistrictIds();

        return parent::getEloquentQuery()->with('pres:id,name')->withCount('districts')
            ->when($ids !== null, fn ($q) => $q->whereHas('districts', fn ($d) => $d->whereIn('id', $ids)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom de la région')->required()->maxLength(120)->unique(ignoreRecord: true),
            Select::make('pres_id')->label('PRES')->required()->searchable()->options(fn () => Pres::orderBy('name')->pluck('name', 'id')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('numero')->label('N°')->rowIndex()->alignCenter()->color('gray')->width('3rem'),
                TextColumn::make('name')->label('Région')->searchable()->sortable()->weight('bold'),
                TextColumn::make('pres.name')->label('PRES')->sortable(),
                TextColumn::make('districts_count')->label('Districts')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('pres_id')->label('PRES')->options(fn () => Pres::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRegions::route('/')];
    }
}
