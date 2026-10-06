<?php

namespace App\Filament\Resources\Signalements;

use App\Domain\Fleet\FieldReports;
use App\Filament\Resources\Signalements\Pages\ListSignalements;
use App\Models\Immobilisation;
use App\Models\Signalement;
use App\Support\PrivatePhoto;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/** Signalements terrain envoyés depuis l'application convoyeur (vidange faite, panne) : le bureau valide ou rejette. */
class SignalementResource extends Resource
{
    protected static ?string $model = Signalement::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Signalements terrain';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'signalement';

    protected static ?string $pluralModelLabel = 'signalements terrain';

    public static function getNavigationBadge(): ?string
    {
        $n = Signalement::where('district_id', Filament::getTenant()?->getKey())->where('statut', 'nouveau')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        $canTreat = fn (Signalement $s) => $s->statut === 'nouveau' && auth()->user()->can('update_vehicles');

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle:id,immatriculation,km_actuel', 'personnel:id,nom_complet', 'traitePar:id,name']))
            ->columns([
                TextColumn::make('signale_at')->label('Signalé le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => Signalement::TYPES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'panne' ? 'danger' : 'info'),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->weight('bold')->searchable(),
                TextColumn::make('km')->label('Km')->numeric(thousandsSeparator: ' ')->placeholder('—'),
                TextColumn::make('description')->label('Détail')->wrap()->limit(80)->placeholder('—')
                    ->description(fn (Signalement $s) => collect([
                        $s->detail('type_vidange') ? 'Vidange '.$s->detail('type_vidange') : null,
                        $s->detail('montant') ? number_format((float) $s->detail('montant'), 0, ',', ' ').' FCFA' : null,
                        $s->detail('prestataire'),
                        $s->detail('motif') ? (Immobilisation::MOTIFS[$s->detail('motif')] ?? $s->detail('motif')) : null,
                    ])->filter()->join(' · ') ?: null),
                TextColumn::make('personnel.nom_complet')->label('Par')->placeholder('—'),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => Signalement::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'nouveau' => 'warning', 'valide' => 'success', default => 'gray' })
                    ->description(fn (Signalement $s) => $s->traitePar ? $s->traitePar->name.($s->commentaire_bureau ? ' — '.$s->commentaire_bureau : '') : null),
            ])
            ->filters([
                SelectFilter::make('statut')->options(Signalement::STATUTS)->default('nouveau'),
                SelectFilter::make('type')->options(Signalement::TYPES),
            ])
            ->recordActions([
                Action::make('photo')->label('Photo')->icon('heroicon-o-camera')->color('gray')
                    ->visible(fn (Signalement $s) => PrivatePhoto::exists($s->photo))
                    ->modalHeading(fn (Signalement $s) => (Signalement::TYPES[$s->type] ?? 'Signalement').' — '.$s->vehicle?->immatriculation)
                    ->modalContent(fn (Signalement $s) => view('filament.modals.photos', ['photos' => [['titre' => $s->description ?: 'Photo du signalement', 'src' => PrivatePhoto::dataUri($s->photo)]]]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer'),
                Action::make('valider')->label('Valider')->icon('heroicon-o-check-circle')->color('success')
                    ->visible($canTreat)
                    ->modalHeading(fn (Signalement $s) => $s->type === 'vidange' ? 'Enregistrer la vidange' : 'Enregistrer l\'immobilisation')
                    ->fillForm(fn (Signalement $s) => [
                        'date' => ($s->signale_at ?? $s->created_at)->toDateString(), 'km' => $s->km,
                        'type_vidange' => $s->detail('type_vidange', 'simple'), 'montant' => $s->detail('montant'), 'prestataire' => $s->detail('prestataire'),
                        'prochain_km' => $s->km ? $s->km + FieldReports::defaultInterval() : null,
                        'motif' => $s->detail('motif', 'panne'), 'description' => $s->description,
                    ])
                    ->schema(fn (Signalement $s) => $s->type === 'vidange' ? [
                        DatePicker::make('date')->label('Date de la vidange')->required()->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                        TextInput::make('km')->label('Kilométrage')->numeric()->required(),
                        Select::make('type_vidange')->label('Type')->options(['simple' => 'Simple', 'complete' => 'Complète', 'revision' => 'Révision'])->required(),
                        TextInput::make('montant')->label('Montant (FCFA)')->numeric(),
                        TextInput::make('prestataire')->maxLength(120),
                        TextInput::make('prochain_km')->label('Prochaine vidange à (km)')->numeric()->required(),
                    ] : [
                        DatePicker::make('date')->label('Immobilisé depuis le')->required()->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                        Select::make('motif')->options(Immobilisation::MOTIFS)->required(),
                        Textarea::make('description')->maxLength(1000),
                    ])
                    ->action(function (Signalement $s, array $data) {
                        try {
                            app(FieldReports::class)->validate($s, auth()->user(), $data);
                            Notification::make()->title($s->type === 'vidange' ? 'Vidange enregistrée' : 'Immobilisation enregistrée : véhicule indisponible')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('rejeter')->label('Rejeter')->icon('heroicon-o-x-circle')->color('gray')
                    ->visible($canTreat)
                    ->schema([Textarea::make('commentaire')->label('Motif du rejet')->required()->maxLength(500)])
                    ->action(function (Signalement $s, array $data) {
                        app(FieldReports::class)->reject($s, auth()->user(), $data['commentaire']);
                        Notification::make()->title('Signalement rejeté')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSignalements::route('/')];
    }
}
