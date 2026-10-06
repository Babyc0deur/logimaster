<?php

namespace App\Filament\Resources\Personnels;

use App\Filament\Resources\Personnels\Pages\CreatePersonnel;
use App\Filament\Resources\Personnels\Pages\EditPersonnel;
use App\Filament\Resources\Personnels\Pages\ListPersonnels;
use App\Filament\Resources\Personnels\Pages\ViewPersonnel;
use App\Filament\Resources\Personnels\RelationManagers\MissionsChefRelationManager;
use App\Filament\Resources\Personnels\RelationManagers\ParticipationsRelationManager;
use App\Filament\Resources\Personnels\RelationManagers\SortiesConduitesRelationManager;
use App\Models\Personnel;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Personnel du district, en une seule liste : chauffeurs, chefs de mission et passagers. Chacun a son accès mobile
 * automatique ; un chauffeur a en plus ses informations de permis (fiche chauffeur liée, tenue à jour d'ici).
 */
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

    protected static ?string $navigationLabel = 'Personnel';

    protected static string|\UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'membre du personnel';

    protected static ?string $pluralModelLabel = 'personnel (chauffeurs, chefs de mission, passagers)';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_personnels');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nom_complet')->label('Nom complet')->required()->maxLength(120),
            Select::make('fonction')->options(Personnel::FONCTIONS)->default('passager')->required()->live(),
            TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
            TextInput::make('email')->email()->maxLength(160),
            Select::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif'])->default('actif')->required(),
            Section::make('Conduite')->description('Informations du chauffeur : permis, véhicule habituel. Le matricule est attribué automatiquement s\'il est laissé vide.')
                ->visible(fn (Get $get) => $get('fonction') === 'chauffeur')
                ->columnSpanFull()->columns(3)->schema([
                    TextInput::make('matricule')->maxLength(30)
                        ->unique(table: 'drivers', column: 'matricule', ignorable: fn (?Personnel $record) => $record?->driver),
                    TextInput::make('categorie_permis')->label('Catégorie de permis')->maxLength(10)->placeholder('B, C, D…'),
                    TextInput::make('numero_permis')->label('N° de permis')->maxLength(40),
                    DatePicker::make('date_obtention_permis')->label('Permis obtenu le')->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('permis_expiration')->label('Permis valable jusqu\'au')->native(false)->displayFormat('d/m/Y'),
                    Select::make('vehicule_principal_id')->label('Véhicule habituel')
                        ->relationship('vehiculePrincipal', 'immatriculation', modifyQueryUsing: fn ($query) => $query->where('district_id', \Filament\Facades\Filament::getTenant()?->getKey()))
                        ->searchable()->preload(),
                ]),
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
            Section::make('Conduite')->columns(4)->visible(fn (Personnel $record) => $record->fonction === 'chauffeur')->schema([
                TextEntry::make('matricule')->placeholder('—'),
                TextEntry::make('categorie_permis')->label('Catégorie de permis')->placeholder('—'),
                TextEntry::make('numero_permis')->label('N° de permis')->placeholder('—'),
                TextEntry::make('permis_expiration')->label('Permis valable jusqu\'au')->date('d/m/Y')->placeholder('—')
                    ->color(fn (Personnel $record) => $record->permis_expiration && $record->permis_expiration->lt(now()->addDays(30)) ? 'danger' : null),
                TextEntry::make('vehiculePrincipal.immatriculation')->label('Véhicule habituel')->placeholder('—'),
                TextEntry::make('fiche_chauffeur')->label('Statistiques et documents')->state('Ouvrir la fiche chauffeur')
                    ->url(fn (Personnel $record) => $record->driver_id ? \App\Filament\Resources\Drivers\DriverResource::getUrl('view', ['record' => $record->driver_id]) : null)->color('primary'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nom_complet')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,personnel_id,email,is_active,must_change_password,last_mobile_login_at')->withCount(['sortiesEnTantQueChef', 'sortiesEnTantQuePassager', 'sortiesConduites']))
            ->recordUrl(fn (Personnel $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('nom_complet')->label('Nom complet')->searchable()->sortable()->weight('bold'),
                TextColumn::make('fonction')->badge()->formatStateUsing(fn ($state) => Personnel::FONCTIONS[$state] ?? $state)->sortable()
                    ->color(fn ($state) => match ($state) { 'chauffeur' => 'info', 'chef_mission' => 'primary', default => 'gray' }),
                TextColumn::make('telephone')->label('Téléphone')->placeholder('—'),
                TextColumn::make('email')->placeholder('—')->toggleable(),
                TextColumn::make('missions')->label('Missions')
                    ->state(fn (Personnel $p) => ($p->sorties_en_tant_que_chef_count ?? 0) + ($p->sorties_en_tant_que_passager_count ?? 0) + ($p->sorties_conduites_count ?? 0)),
                TextColumn::make('permis_expiration')->label('Permis jusqu\'au')->date('d/m/Y')->placeholder('—')->toggleable()
                    ->color(fn (Personnel $p) => $p->permis_expiration && $p->permis_expiration->lt(now()->addDays(30)) ? 'danger' : null),
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
        return [SortiesConduitesRelationManager::class, MissionsChefRelationManager::class, ParticipationsRelationManager::class];
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
