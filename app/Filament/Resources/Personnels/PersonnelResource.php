<?php

namespace App\Filament\Resources\Personnels;

use App\Filament\Resources\Personnels\Pages\CreatePersonnel;
use App\Filament\Resources\Personnels\Pages\EditPersonnel;
use App\Filament\Resources\Personnels\Pages\ListPersonnels;
use App\Filament\Resources\Personnels\Pages\ViewPersonnel;
use App\Filament\Resources\Personnels\RelationManagers\MissionsChefRelationManager;
use App\Filament\Resources\Personnels\RelationManagers\ParticipationsRelationManager;
use App\Models\Personnel;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Autres personnels : chefs de mission et passagers (fiche individuelle, historique des participations). */
class PersonnelResource extends Resource
{
    /** Accessible depuis le menu utilisateur (en haut), au-dessus de « Déconnexion ». */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $model = Personnel::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Chefs de mission & passagers';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'membre du personnel';

    protected static ?string $pluralModelLabel = 'chefs de mission & passagers';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_personnels');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom_complet')->label('Nom complet')->required()->maxLength(120),
            Select::make('fonction')->options(Personnel::FONCTIONS)->default('passager')->required(),
            TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
            TextInput::make('email')->email()->maxLength(160),
            Select::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif'])->default('actif')->required(),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fiche')->columns(4)->schema([
                TextEntry::make('nom_complet')->label('Nom')->weight('bold'),
                TextEntry::make('fonction')->badge()->formatStateUsing(fn ($state) => Personnel::FONCTIONS[$state] ?? $state),
                TextEntry::make('telephone')->label('Téléphone')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('statut')->badge(),
                TextEntry::make('district.name')->label('District'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nom_complet')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,personnel_id,email,is_active,must_change_password,last_mobile_login_at')->withCount(['sortiesEnTantQueChef', 'sortiesEnTantQuePassager']))
            ->recordUrl(fn (Personnel $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nom_complet')->label('Nom complet')->searchable()->sortable()->weight('bold'),
                TextColumn::make('fonction')->badge()->formatStateUsing(fn ($state) => Personnel::FONCTIONS[$state] ?? $state)->sortable(),
                TextColumn::make('telephone')->label('Téléphone')->placeholder('—'),
                TextColumn::make('email')->placeholder('—')->toggleable(),
                TextColumn::make('missions')->label('Missions')
                    ->state(fn (Personnel $p) => ($p->sorties_en_tant_que_chef_count ?? 0) + ($p->sorties_en_tant_que_passager_count ?? 0)),
                TextColumn::make('identifiant')->label('Identifiant mobile')->copyable()->placeholder('—')->toggleable(),
                TextColumn::make('acces_mobile')->label('Accès mobile')->badge()
                    ->state(fn (Personnel $p) => match (true) {
                        ! $p->user => 'Aucun',
                        ! $p->user->is_active => 'Suspendu',
                        $p->user->must_change_password => 'Code à remettre',
                        default => 'Actif',
                    })
                    ->color(fn ($state) => match ($state) { 'Actif' => 'success', 'Code à remettre' => 'warning', 'Suspendu' => 'danger', default => 'gray' })
                    ->tooltip(fn (Personnel $p) => $p->user?->last_mobile_login_at ? 'Dernière connexion : '.$p->user->last_mobile_login_at->format('d/m/Y H:i') : null),
                TextColumn::make('statut')->badge()->color(fn ($state) => $state === 'actif' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('fonction')->options(Personnel::FONCTIONS),
                SelectFilter::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif']),
            ])
            ->recordActions([ViewAction::make()->label('Fiche'), EditAction::make(), \App\Filament\Support\MobileAccessActions::credentials(), \App\Filament\Support\MobileAccessActions::resetCode()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [MissionsChefRelationManager::class, ParticipationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonnels::route('/'),
            'create' => CreatePersonnel::route('/create'),
            'view' => ViewPersonnel::route('/{record}'),
            'edit' => EditPersonnel::route('/{record}/edit'),
        ];
    }
}
