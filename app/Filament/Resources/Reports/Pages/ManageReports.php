<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Domain\Reports\ReportService;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageReports extends ManageRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generer')->label('Générer un rapport')->icon('heroicon-o-document-plus')
                ->visible(fn () => auth()->user()->can('create_reports'))
                ->modalSubmitActionLabel('Générer')
                ->schema([
                    ...ReportResource::generationFields(),
                    Select::make('format')->label('Format')->options(['pdf' => 'PDF', 'xlsx' => 'Excel (.xlsx)'])->default('pdf')->required()->native(false),
                    Toggle::make('envoyer')->label('Envoyer aussi par email')->live(),
                    TagsInput::make('destinataires')->label('Destinataires (emails)')->nestedRecursiveRules(['email'])->visible(fn ($get) => (bool) $get('envoyer'))->required(fn ($get) => (bool) $get('envoyer')),
                ])
                ->action(function (array $data) {
                    $service = app(ReportService::class);
                    $report = $service->generate(auth()->user(), $data['type'], $data['format'], CarbonImmutable::createFromFormat('Y-m', $data['periode']), ReportResource::scopeFrom($data));
                    if (! empty($data['envoyer']) && ! empty($data['destinataires'])) {
                        $service->email([$report], $data['destinataires']);
                        Notification::make()->title('Rapport envoyé à '.count($data['destinataires']).' destinataire(s)')->success()->send();
                    }

                    return Storage::disk('local')->download($report->fichier_path, $service->filename($report));
                }),

            Action::make('planifier')->label('Planifier un envoi')->icon('heroicon-o-clock')->color('gray')
                ->visible(fn () => auth()->user()->can('create_reports'))
                ->modalDescription('Le rapport est généré et envoyé automatiquement : mensuel (le jour choisi, pour le mois écoulé) ou hebdomadaire (pour le mois en cours).')
                ->schema([
                    ...ReportResource::generationFields(withMonth: false),
                    CheckboxList::make('formats')->label('Formats')->options(['pdf' => 'PDF', 'xlsx' => 'Excel'])->default(['pdf'])->required(),
                    Select::make('frequence')->label('Fréquence')->options(['mensuelle' => 'Mensuelle', 'hebdomadaire' => 'Hebdomadaire'])->default('mensuelle')->required()->live()->native(false),
                    Select::make('jour')->label('Jour')->required()->native(false)->default(1)
                        ->options(fn ($get) => $get('frequence') === 'hebdomadaire'
                            ? [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche']
                            : array_combine(range(1, 28), array_map(fn ($d) => "Le {$d} du mois", range(1, 28)))),
                    TagsInput::make('destinataires')->label('Destinataires (emails)')->required()->nestedRecursiveRules(['email']),
                ])
                ->action(function (array $data) {
                    ReportSchedule::create([
                        'user_id' => auth()->id(), 'type' => $data['type'], 'formats' => $data['formats'], 'scope' => ReportResource::scopeFrom($data),
                        'frequence' => $data['frequence'], 'jour' => (int) $data['jour'], 'destinataires' => $data['destinataires'],
                    ]);
                    Notification::make()->title('Envoi planifié')->success()->send();
                }),
        ];
    }
}
