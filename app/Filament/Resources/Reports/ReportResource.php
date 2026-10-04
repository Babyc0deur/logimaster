<?php

namespace App\Filament\Resources\Reports;

use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportService;
use App\Filament\Concerns\ScopeFilters;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\Report;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/** Rapports générés : historique, téléchargement, envoi par email. La génération se lance depuis la barre d'actions. */
class ReportResource extends Resource
{
    use ScopeFilters;

    protected static ?string $model = Report::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Rapports';

    protected static string|\UnitEnum|null $navigationGroup = 'Données';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'rapport';

    protected static ?string $pluralModelLabel = 'rapports';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_reports');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! auth()->user()->isNational(), fn ($q) => $q->where('user_id', auth()->id()));
    }

    /** Formulaire de génération / planification (type, mois, périmètre, format). */
    public static function generationFields(bool $withMonth = true): array
    {
        return [
            Select::make('type')->label('Rapport')->options(ReportBuilder::types())->default('ddkm')->required()->native(false),
            ...($withMonth ? [Select::make('periode')->label('Mois')->native(false)->required()->default(now()->subMonthNoOverflow()->format('Y-m'))
                ->options(collect(range(0, 23))->mapWithKeys(fn ($i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => ucfirst(now()->startOfMonth()->subMonths($i)->translatedFormat('F Y'))])->all())] : []),
            ...self::scopeFilterFields(),
        ];
    }

    public static function scopeFrom(array $data): ?array
    {
        return array_filter(['pres_id' => $data['pres_id'] ?? null, 'region_id' => $data['region_id'] ?? null, 'district_id' => $data['district_id'] ?? null]) ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('titre')->label('Rapport')->searchable()->wrap(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => ReportBuilder::TYPES[$state] ?? $state)->toggleable(),
                TextColumn::make('periode')->label('Mois')->formatStateUsing(fn ($state) => ucfirst(CarbonImmutable::createFromFormat('Y-m', $state)->translatedFormat('F Y')))->sortable(),
                TextColumn::make('format')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('user.name')->label('Par')->toggleable(),
                TextColumn::make('created_at')->label('Généré le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(ReportBuilder::types()),
                SelectFilter::make('format')->options(['pdf' => 'PDF', 'xlsx' => 'Excel']),
            ])
            ->recordActions([
                Action::make('telecharger')->label('Télécharger')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Report $r) => $r->exists_on_disk())
                    ->action(fn (Report $r) => Storage::disk('local')->download($r->fichier_path, app(ReportService::class)->filename($r))),
                Action::make('envoyer')->label('Envoyer par email')->icon('heroicon-o-envelope')
                    ->visible(fn (Report $r) => $r->exists_on_disk() && auth()->user()->can('create_reports'))
                    ->schema([TagsInput::make('destinataires')->label('Destinataires (emails)')->required()->nestedRecursiveRules(['email'])->placeholder('prenom.nom@exemple.org')])
                    ->action(function (Report $r, array $data) {
                        app(ReportService::class)->email([$r], $data['destinataires']);
                        Notification::make()->title('Rapport envoyé à '.count($data['destinataires']).' destinataire(s)')->success()->send();
                    }),
                DeleteAction::make()->visible(fn () => auth()->user()->can('create_reports'))
                    ->before(fn (Report $r) => $r->fichier_path && Storage::disk('local')->delete($r->fichier_path)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReports::route('/')];
    }
}
