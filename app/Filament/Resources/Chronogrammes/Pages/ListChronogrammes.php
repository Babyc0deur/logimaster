<?php

namespace App\Filament\Resources\Chronogrammes\Pages;

use App\Domain\Chronogramme\ChronogrammeGenerator;
use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Filament\Resources\Chronogrammes\ChronogrammeResource;
use App\Filament\Resources\Chronogrammes\Widgets\ChronogrammeWeekGrid;
use App\Filament\Support\ImportExportActions;
use App\Models\Driver;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListChronogrammes extends ListRecords
{
    protected static string $resource = ChronogrammeResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ChronogrammeWeekGrid::class];
    }

    protected function getHeaderActions(): array
    {
        // Trois boutons : créer, valider le mois, et le reste (génération automatique, import / export) dans « Plus »
        return [
            CreateAction::make()->label('Nouvelle sortie'),
            ActionGroup::make(self::workflowActions())->label('Validation du mois')->icon('heroicon-o-check-badge')->button()->color('info'),
            ActionGroup::make([
            Action::make('generer')
                ->label('Générer selon la fréquence')
                ->icon('heroicon-o-sparkles')
                ->modalDescription('Crée les sorties du mois pour chaque circuit actif, selon sa fréquence. Les dates déjà planifiées sont ignorées.')
                ->schema([
                    Select::make('mois')->label('Mois')->required()
                        ->options(collect(range(0, 3))->mapWithKeys(fn ($i) => [
                            now()->startOfMonth()->addMonths($i)->format('Y-m') => now()->startOfMonth()->addMonths($i)->translatedFormat('F Y'),
                        ])->all())
                        ->default(now()->format('Y-m')),
                    Select::make('vehicle_id')->label('Véhicule')->required()
                        ->options(fn () => Vehicle::where('district_id', Filament::getTenant()->getKey())->pluck('immatriculation', 'id')),
                    Select::make('driver_id')->label('Chauffeur')
                        ->options(fn () => Driver::where('district_id', Filament::getTenant()->getKey())->pluck('nom_complet', 'id')),
                    Select::make('jour')->label('Jour de livraison')->required()->default(1)
                        ->options([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi']),
                ])
                ->action(function (array $data) {
                    $count = app(ChronogrammeGenerator::class)->generate(
                        Filament::getTenant()->getKey(),
                        CarbonImmutable::createFromFormat('Y-m', $data['mois'])->startOfMonth(),
                        $data['vehicle_id'],
                        $data['driver_id'] ?? null,
                        (int) $data['jour'],
                    );
                    Notification::make()->title("{$count} sortie(s) planifiée(s)")->success()->send();
                }),
                ImportExportActions::group('chronogrammes', 'chronogramme', 'Chronogramme')->dropdown(false),
            ])->label('Plus')->icon('heroicon-m-ellipsis-horizontal')->button()->color('gray'),
        ];
    }

    /** Soumission / validation / refus / levée de validation pour un mois (district courant). */
    private static function workflowActions(): array
    {
        $monthField = fn () => Select::make('mois')->label('Mois')->required()
            ->options(collect(range(-1, 3))->mapWithKeys(fn ($i) => [
                now()->startOfMonth()->addMonths($i)->format('Y-m') => now()->startOfMonth()->addMonths($i)->translatedFormat('F Y'),
            ])->all())->default(now()->format('Y-m'));
        $run = function (string $method, string $done, bool $withMotif = false) {
            return function (array $data) use ($method, $done, $withMotif) {
                $args = [Filament::getTenant()->getKey(), CarbonImmutable::createFromFormat('Y-m', $data['mois'])->startOfMonth(), auth()->user()];
                $withMotif && $args[] = $data['motif'];
                $n = app(ChronogrammeWorkflow::class)->{$method}(...$args);
                Notification::make()->title($n ? "{$n} sortie(s) {$done}" : 'Aucune sortie concernée')->color($n ? 'success' : 'warning')->send();
            };
        };

        return [
            Action::make('soumettre')->label('Soumettre à validation')->icon('heroicon-o-paper-airplane')->color('info')
                ->visible(fn () => auth()->user()->can('update_chronogrammes'))
                ->modalDescription('Envoie le planning du mois au superviseur pour validation.')
                ->schema([$monthField()])->action($run('submit', 'soumise(s) à validation')),
            Action::make('valider')->label('Valider le mois')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => auth()->user()->can('validate_chronogrammes'))
                ->modalDescription('Valide et verrouille les sorties soumises du mois.')
                ->schema([$monthField()])->action($run('validate', 'validée(s)')),
            Action::make('refuser')->label('Refuser le mois')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => auth()->user()->can('validate_chronogrammes'))
                ->schema([$monthField(), Textarea::make('motif')->label('Motif du refus')->required()])
                ->action($run('refuse', 'refusée(s)', true)),
            Action::make('rouvrir')->label('Lever la validation')->icon('heroicon-o-lock-open')->color('gray')
                ->visible(fn () => auth()->user()->can('validate_chronogrammes'))
                ->requiresConfirmation()->schema([$monthField()])->action($run('reopen', 'rouverte(s)')),
        ];
    }
}
