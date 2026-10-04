<?php

namespace App\Filament\Resources\Livraisons;

use App\Filament\Resources\Livraisons\Pages\ListLivraisons;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\Espc;
use App\Models\LivraisonEspc;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Suivi des livraisons ESPC : livraisons planifiées au chronogramme vs réalisées, retards, raisons de non-livraison. */
class LivraisonResource extends Resource
{
    protected static ?string $model = LivraisonEspc::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Suivi des livraisons';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'livraison';

    protected static ?string $pluralModelLabel = 'livraisons ESPC';

    /** Vue tableau de « Suivi des livraisons » : accessible depuis la page principale, pas dans le menu. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->whereHas('chronogramme', fn ($q) => $q->where('district_id', $tenant?->getKey())->where('statut', '!=', 'annulee'))
            ->with(['chronogramme.circuit:id,nom', 'espc:id,nom,type']);
    }

    private static function planDate(): \Illuminate\Database\Eloquent\Builder
    {
        return Chronogramme::select('date_prevue')->whereColumn('chronogrammes.id', 'livraisons_espc.chronogramme_id');
    }

    public static function table(Table $table): Table
    {
        $can = fn () => auth()->user()->can('update_chronogrammes');

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc(self::planDate()))
            ->columns([
                TextColumn::make('chronogramme.date_prevue')->label('Date prévue')->date('d/m/Y')->sortable(query: fn (Builder $q, string $dir) => $q->orderBy(self::planDate(), $dir)),
                TextColumn::make('chronogramme.circuit.nom')->label('Circuit')->placeholder('—'),
                TextColumn::make('espc.nom')->label('ESPC')->weight('bold')->searchable(),
                TextColumn::make('ordre')->label('Étape')->prefix('n° '),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => LivraisonEspc::STATUTS[$state] ?? $state)
                    ->color(fn (LivraisonEspc $l) => match (true) {
                        $l->statut === 'livre' => 'success',
                        $l->statut === 'non_livre' => 'danger',
                        $l->chronogramme?->date_prevue?->isPast() => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('date_livraison')->label('Livrée le')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('lieu_livraison')->label('Lieu')->formatStateUsing(fn ($state) => LivraisonEspc::LIEUX[$state] ?? $state)->placeholder('—'),
                TextColumn::make('retard')->label('Retard')->state(fn (LivraisonEspc $l) => $l->statut === 'livre' ? ($l->retard_jours > 0 ? "{$l->retard_jours} j" : 'Aucun') : '—')
                    ->color(fn (LivraisonEspc $l) => $l->retard_jours > 0 ? 'danger' : null),
                TextColumn::make('raison_non_livraison')->label('Raison / commentaire')->placeholder('—')->wrap()->limit(60),
            ])
            ->filters([
                SelectFilter::make('statut')->options(LivraisonEspc::STATUTS)->multiple(),
                SelectFilter::make('circuit')->label('Circuit')->options(fn () => Circuit::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id'))
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $v) => $q->whereIn('chronogramme_id', Chronogramme::select('id')->where('circuit_id', $v)))),
                SelectFilter::make('espc_id')->label('ESPC')->searchable()->options(fn () => Espc::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id')),
                Filter::make('periode')->label('Période')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom() ?? now()->startOfMonth()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q->whereIn('chronogramme_id', Chronogramme::select('id')
                        ->when($data['du'] ?? null, fn ($c, $v) => $c->whereDate('date_prevue', '>=', $v))
                        ->when($data['au'] ?? null, fn ($c, $v) => $c->whereDate('date_prevue', '<=', $v)))),
                Filter::make('en_retard')->label('À traiter / en retard')->toggle()
                    ->query(fn (Builder $q) => $q->where('statut', 'planifie')->whereIn('chronogramme_id', Chronogramme::select('id')->whereDate('date_prevue', '<', today()))),
            ])
            ->recordActions([
                Action::make('livre')->label('Marquer livrée')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (LivraisonEspc $l) => $can() && $l->statut !== 'livre')
                    ->fillForm(fn (LivraisonEspc $l) => ['date_livraison' => $l->chronogramme->date_prevue->toDateString(), 'lieu_livraison' => 'site'])
                    ->schema([
                        DatePicker::make('date_livraison')->label('Date de livraison')->required()->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                        Select::make('lieu_livraison')->label('Lieu')->options(LivraisonEspc::LIEUX)->required(),
                        Textarea::make('raison_non_livraison')->label('Commentaire (obligatoire si livrée en transit ou en retard)')->maxLength(500),
                    ])
                    ->action(function (LivraisonEspc $l, array $data) {
                        $l->update(['statut' => 'livre', 'date_livraison' => $data['date_livraison'], 'lieu_livraison' => $data['lieu_livraison'], 'raison_non_livraison' => $data['raison_non_livraison'] ?? null]);
                        Notification::make()->title('Livraison enregistrée')->success()->send();
                    }),
                Action::make('non_livre')->label('Non livrée')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (LivraisonEspc $l) => $can() && $l->statut !== 'non_livre')
                    ->schema([Textarea::make('raison')->label('Raison de la non-livraison')->required()->maxLength(500)->placeholder('Panne véhicule, route impraticable, report bailleur…')])
                    ->action(function (LivraisonEspc $l, array $data) {
                        $l->update(['statut' => 'non_livre', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => $data['raison']]);
                        Notification::make()->title('Non-livraison enregistrée')->warning()->send();
                    }),
                Action::make('reinitialiser')->label('Remettre en planifiée')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (LivraisonEspc $l) => $can() && $l->statut !== 'planifie')->requiresConfirmation()
                    ->action(fn (LivraisonEspc $l) => $l->update(['statut' => 'planifie', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => null])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('livre_masse')->label('Marquer livrées (sur site, à la date prévue)')->icon('heroicon-o-check-circle')
                        ->visible($can)->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $n = 0;
                            foreach ($records->where('statut', 'planifie') as $l) {
                                $l->update(['statut' => 'livre', 'date_livraison' => $l->chronogramme->date_prevue->toDateString(), 'lieu_livraison' => 'site']);
                                $n++;
                            }
                            Notification::make()->title("{$n} livraison(s) marquée(s) livrée(s)")->success()->send();
                        })->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLivraisons::route('/')];
    }
}
