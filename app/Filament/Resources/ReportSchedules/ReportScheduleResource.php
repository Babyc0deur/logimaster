<?php

namespace App\Filament\Resources\ReportSchedules;

use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportScope;
use App\Domain\Reports\ReportService;
use App\Filament\Resources\ReportSchedules\Pages\ManageReportSchedules;
use App\Models\ReportSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Envois récurrents de rapports par email. */
class ReportScheduleResource extends Resource
{
    protected static ?string $model = ReportSchedule::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Envois planifiés';

    protected static string|\UnitEnum|null $navigationGroup = 'Données';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'envoi planifié';

    protected static ?string $pluralModelLabel = 'envois planifiés';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_reports');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! auth()->user()->isNational(), fn ($q) => $q->where('user_id', auth()->id()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->label('Rapport')->formatStateUsing(fn ($state) => ReportBuilder::TYPES[$state] ?? $state)->wrap(),
                TextColumn::make('scope')->label('Périmètre')->state(fn (ReportSchedule $s) => ReportScope::label($s->scope, count(ReportScope::ids($s->scope, $s->user))))->wrap(),
                TextColumn::make('formats')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('frequence')->label('Fréquence')
                    ->state(fn (ReportSchedule $s) => $s->frequence === 'hebdomadaire'
                        ? 'Chaque '.['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][$s->jour]
                        : "Le {$s->jour} du mois"),
                TextColumn::make('destinataires')->label('Destinataires')->badge()->color('gray'),
                TextColumn::make('dernier_envoi_at')->label('Dernier envoi')->dateTime('d/m/Y H:i')->placeholder('Jamais'),
                ToggleColumn::make('actif')->label('Actif')->disabled(fn () => ! auth()->user()->can('create_reports')),
            ])
            ->recordActions([
                Action::make('envoyer_maintenant')->label('Envoyer maintenant')->icon('heroicon-o-paper-airplane')
                    ->visible(fn () => auth()->user()->can('create_reports'))
                    ->requiresConfirmation()
                    ->action(function (ReportSchedule $s) {
                        $service = app(ReportService::class);
                        $month = $s->frequence === 'hebdomadaire' ? now()->startOfMonth()->toImmutable() : now()->subMonthNoOverflow()->startOfMonth()->toImmutable();
                        $reports = array_map(fn ($f) => $service->generate($s->user, $s->type, $f, $month, $s->scope), $s->formats);
                        $service->email($reports, $s->destinataires);
                        $s->forceFill(['dernier_envoi_at' => now()])->save();
                        Notification::make()->title('Rapport envoyé à '.count($s->destinataires).' destinataire(s)')->success()->send();
                    }),
                DeleteAction::make()->visible(fn () => auth()->user()->can('create_reports')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReportSchedules::route('/')];
    }
}
