<?php

namespace App\Filament\Resources\Budgets;

use App\Domain\Finance\BudgetTracker;
use App\Filament\Resources\Budgets\Pages\ManageBudgets;
use App\Models\Budget;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/** Budgets mensuels par poste (carburant, maintenance, autres frais) et par bailleur, avec consommation et prévision. */
class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Budgets';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'budget';

    protected static ?string $pluralModelLabel = 'budgets';

    public static function canAccess(): bool
    {
        return \App\Support\Modules::enabled('finance') && (bool) auth()->user()?->can('view_budgets');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')->label('Mois')->required()->native(false)
                ->options(collect(range(-3, 12))->mapWithKeys(fn ($i) => [
                    now()->startOfMonth()->addMonths($i)->toDateString() => ucfirst(now()->startOfMonth()->addMonths($i)->translatedFormat('F Y')),
                ])->all()),
            Select::make('poste')->label('Poste')->options(Budget::POSTES)->default('global')->required()->live(),
            Select::make('bailleur')->label('Bailleur')->searchable()->native(false)->placeholder('Tous bailleurs')
                ->options(fn () => Vehicle::where('district_id', Filament::getTenant()?->getKey())->whereNotNull('bailleur')->where('bailleur', '!=', '')
                    ->distinct()->pluck('bailleur', 'bailleur')->all())
                ->createOptionForm([TextInput::make('nom')->required()->maxLength(120)])
                ->createOptionUsing(fn (array $data) => trim($data['nom']))
                ->helperText('Laissez vide pour un budget tous bailleurs confondus.')
                ->dehydrateStateUsing(fn ($state) => $state ?? '')
                ->rules([fn (Get $get, ?Budget $record) => function ($attribute, $value, $fail) use ($get, $record) {
                    $exists = Budget::where('district_id', Filament::getTenant()->getKey())->whereDate('period', $get('period'))
                        ->where('poste', $get('poste'))->where('bailleur', $value ?? '')
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
                    $exists && $fail('Un budget existe déjà pour ce mois, ce poste et ce bailleur.');
                }]),
            TextInput::make('montant_alloue')->label('Montant alloué')->numeric()->required()->minValue(0)->suffix('FCFA'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $status = fn (Budget $b) => app(BudgetTracker::class)->status($b);

        return $table
            ->defaultSort('period', 'desc')
            ->columns([
                TextColumn::make('period')->label('Mois')->formatStateUsing(fn ($state) => ucfirst(CarbonImmutable::parse($state)->translatedFormat('F Y')))->sortable(),
                TextColumn::make('poste')->badge()->formatStateUsing(fn ($state) => Budget::POSTES[$state] ?? $state),
                TextColumn::make('bailleur')->placeholder('Tous')->formatStateUsing(fn ($state) => $state ?: 'Tous'),
                TextColumn::make('montant_alloue')->label('Alloué')->money('XOF'),
                TextColumn::make('depense')->label('Dépensé')->money('XOF')->state(fn (Budget $b) => $status($b)['depense']),
                TextColumn::make('reste')->label('Reste')->money('XOF')->state(fn (Budget $b) => $status($b)['reste'])
                    ->color(fn (Budget $b) => $status($b)['reste'] < 0 ? 'danger' : null),
                TextColumn::make('pct')->label('Consommé')->badge()
                    ->state(fn (Budget $b) => ($p = $status($b)['pct']) === null ? '—' : round($p).' %')
                    ->color(fn (Budget $b) => match ($status($b)['niveau']) { 'depasse', 'prevision_depassement' => 'danger', 'attention' => 'warning', default => 'success' }),
                TextColumn::make('prevision')->label('Prévision fin de mois')->money('XOF')->state(fn (Budget $b) => $status($b)['prevision']),
                TextColumn::make('niveau')->label('Situation')->badge()
                    ->state(fn (Budget $b) => ['depasse' => 'Dépassé', 'prevision_depassement' => 'Dépassement prévu', 'attention' => 'À surveiller', 'ok' => 'Dans le budget'][$status($b)['niveau']])
                    ->color(fn (Budget $b) => match ($status($b)['niveau']) { 'depasse', 'prevision_depassement' => 'danger', 'attention' => 'warning', default => 'success' }),
            ])
            ->filters([
                SelectFilter::make('poste')->options(Budget::POSTES),
                SelectFilter::make('period')->label('Mois')->options(fn () => Budget::query()->orderByDesc('period')->pluck('period')->unique()
                    ->mapWithKeys(fn ($p) => [CarbonImmutable::parse($p)->toDateString() => ucfirst(CarbonImmutable::parse($p)->translatedFormat('F Y'))])->all())
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($q, $v) => $q->whereDate('period', $v))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('reporter')->label('Reporter au mois suivant')->icon('heroicon-o-forward')
                        ->visible(fn () => auth()->user()->can('create_budgets'))
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $n = 0;
                            foreach ($records as $b) {
                                $next = CarbonImmutable::parse($b->period)->addMonthNoOverflow()->startOfMonth();
                                $created = Budget::firstOrCreate(
                                    ['district_id' => $b->district_id, 'period' => $next->toDateString(), 'poste' => $b->poste, 'bailleur' => $b->bailleur],
                                    ['montant_alloue' => $b->montant_alloue]
                                );
                                $n += $created->wasRecentlyCreated ? 1 : 0;
                            }
                            Notification::make()->title("{$n} budget(s) reporté(s) au mois suivant")->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBudgets::route('/')];
    }
}
