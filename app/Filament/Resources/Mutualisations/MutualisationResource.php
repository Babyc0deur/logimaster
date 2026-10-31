<?php

namespace App\Filament\Resources\Mutualisations;

use App\Filament\Pages\ActiviteVehicule;
use App\Filament\Resources\Mutualisations\Pages\CreateMutualisation;
use App\Filament\Resources\Mutualisations\Pages\EditMutualisation;
use App\Filament\Resources\Mutualisations\Pages\ListMutualisations;
use App\Models\District;
use App\Models\Vehicle;
use App\Models\VehicleMutualisation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mutualisation de véhicules : un district met un de ses véhicules à la disposition d'un autre district pour une période.
 * La liste montre les véhicules **prêtés** par le district choisi et ceux qu'il **emprunte** ; les deux districts accèdent à l'activité du véhicule.
 */
class MutualisationResource extends Resource
{
    protected static ?string $model = VehicleMutualisation::class;

    /** Visible par le district propriétaire ET par le district utilisateur : le filtre se fait dans getEloquentQuery(). */
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Mutualisation';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'mise à disposition';

    protected static ?string $pluralModelLabel = 'Mutualisation de véhicules';

    protected static ?string $slug = 'mutualisations';

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()->with(['vehicle.district:id,name', 'district:id,name'])
            ->where(fn (Builder $q) => $q->where('district_id', $tenant)->orWhereHas('vehicle', fn (Builder $v) => $v->withTrashed()->where('district_id', $tenant)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')->label('Véhicule prêté')->required()->searchable()->live()
                ->options(fn () => Vehicle::where('district_id', Filament::getTenant()?->getKey())->orderBy('immatriculation')->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->immatriculation.($v->marque ? ' · '.trim($v->marque.' '.$v->modele) : '')])->all())
                ->disabledOn('edit')->helperText('Un véhicule de votre district.'),
            Select::make('district_id')->label('District qui l\'utilise')->required()->searchable()
                ->options(fn () => District::where('id', '!=', Filament::getTenant()?->getKey())->orderBy('name')->pluck('name', 'id')->all())
                ->helperText('Il verra ce que fait le véhicule pendant la période, et vous verrez ce qu\'il en fait.'),
            DatePicker::make('date_debut')->label('Du')->native(false)->displayFormat('d/m/Y')->default(now())->required()->live()
                ->rules([fn (Get $get, ?\Illuminate\Database\Eloquent\Model $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    if ($get('vehicle_id') && VehicleMutualisation::overlaps($get('vehicle_id'), (string) $value, $get('date_fin') ?: null, $record?->getKey())) {
                        $fail('Ce véhicule est déjà mis à disposition sur une partie de cette période.');
                    }
                }]),
            DatePicker::make('date_fin')->label('Au (facultatif)')->native(false)->displayFormat('d/m/Y')->afterOrEqual('date_debut')
                ->helperText('Vide : jusqu\'à ce que vous y mettiez fin.'),
            Textarea::make('motif')->label('Motif ou convention')->maxLength(255)->columnSpanFull()->placeholder('Ex. appui au district pour les enlèvements NPSP'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $tenant = fn () => Filament::getTenant()?->getKey();

        return $table->defaultSort('date_debut', 'desc')->poll('60s')
            ->columns([
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->weight('bold')->searchable(),
                TextColumn::make('sens')->label('')->badge()
                    ->state(fn (VehicleMutualisation $m) => $m->district_id === $tenant() ? 'Emprunté' : 'Prêté')
                    ->color(fn ($state) => $state === 'Prêté' ? 'warning' : 'info'),
                TextColumn::make('vehicle.district.name')->label('Propriétaire'),
                TextColumn::make('district.name')->label('Utilisateur'),
                TextColumn::make('date_debut')->label('Du')->date('d/m/Y')->sortable(),
                TextColumn::make('date_fin')->label('Au')->date('d/m/Y')->placeholder('sans fin'),
                TextColumn::make('etat')->label('État')->badge()
                    ->state(fn (VehicleMutualisation $m) => VehicleMutualisation::ETATS[$m->etat()])
                    ->color(fn ($state) => match ($state) { 'En cours' => 'success', 'À venir' => 'info', default => 'gray' }),
                TextColumn::make('motif')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('sens')->label('Sens')->options(['prete' => 'Prêtés par mon district', 'empruntes' => 'Empruntés par mon district'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'prete' => $query->whereHas('vehicle', fn (Builder $v) => $v->withTrashed()->where('district_id', $tenant())),
                        'empruntes' => $query->where('district_id', $tenant()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('activite')->label('Activité')->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(fn (VehicleMutualisation $m) => ActiviteVehicule::getUrl(['vehicule' => $m->vehicle_id])),
                Action::make('terminer')->label('Terminer')->icon('heroicon-o-stop-circle')->color('warning')
                    ->visible(fn (VehicleMutualisation $m) => $m->statut === 'active' && $m->etat() !== 'terminee' && auth()->user()?->can('update', $m))
                    ->requiresConfirmation()->modalHeading('Mettre fin à la mise à disposition ?')
                    ->modalDescription('Le véhicule n\'est plus à la disposition de ce district à partir d\'aujourd\'hui. L\'historique reste visible pour les deux districts.')
                    ->action(function (VehicleMutualisation $m) {
                        $end = today();
                        $m->update(['statut' => 'terminee', 'date_fin' => $m->date_debut->gt($end) ? $m->date_debut : $end]);
                        Notification::make()->title('Mise à disposition terminée')->success()->send();
                    }),
                EditAction::make()->label('Modifier')->visible(fn (VehicleMutualisation $m) => auth()->user()?->can('update', $m)),
                DeleteAction::make()->visible(fn (VehicleMutualisation $m) => auth()->user()?->can('delete', $m)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMutualisations::route('/'),
            'create' => CreateMutualisation::route('/create'),
            'edit' => EditMutualisation::route('/{record}/edit'),
        ];
    }
}
