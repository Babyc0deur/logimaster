<?php

namespace App\Filament\Pages;

use App\Domain\Import\Workbook\WorkbookExporter;
use App\Domain\Import\Workbook\WorkbookImporter;
use App\Domain\Import\Workbook\WorkbookReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/** Import / export du classeur Excel Logimaster (tous les onglets d'un district en une fois). */
class WorkbookImportPage extends Page
{
    protected string $view = 'filament.pages.workbook-import';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?string $navigationLabel = 'Import / export du classeur';

    protected static string|\UnitEnum|null $navigationGroup = 'Données';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Import / export du classeur Logimaster';

    protected static ?string $slug = 'import-classeur';

    public ?array $data = [];

    /** @var array<string, mixed>|null résultat du dernier import */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('create_vehicles');
    }

    public function mount(): void
    {
        $this->form->fill(['ignorer_erreurs' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Importer un classeur')
                ->description("Fichier Excel Logimaster du district courant (.xlsx ou .xlsm). Onglets lus : Liste des Sites, VEHICULES, CHRONOGRAMME, CIRCUITS (sorties), CARBURANT, AUTRES FRAIS, VIDANGES, IMMOBILISATION. Les formules, le DASHBOARD et la CONSOLIDATION sont ignorés (recalculés par l'application). Le fichier peut être réimporté sans créer de doublons.")
                ->schema([
                    FileUpload::make('fichier')->label('Classeur')->required()->disk('local')->directory('imports')->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel.sheet.macroEnabled.12', 'application/vnd.ms-excel', 'application/octet-stream',
                        ])->maxSize(51200),
                    Toggle::make('ignorer_erreurs')->label('Ignorer les lignes en erreur')
                        ->helperText("Désactivé : si une ligne est invalide, rien n'est importé (tout ou rien) et la liste des erreurs est affichée."),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('modele')->label('Télécharger le modèle vide')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => response()->download((new WorkbookExporter)->template(), 'modele-logimaster.xlsx')->deleteFileAfterSend()),
            Action::make('export')->label('Exporter les données du district')->icon('heroicon-o-arrow-up-on-square')->color('gray')
                ->action(fn () => response()->download(
                    (new WorkbookExporter)->export(Filament::getTenant()->getKey()),
                    'logimaster-'.str(Filament::getTenant()->name)->slug().'-'.now()->format('Ymd').'.xlsx'
                )->deleteFileAfterSend()),
        ];
    }

    public function import(): void
    {
        $state = $this->form->getState();
        $path = Storage::disk('local')->path($state['fichier']);

        try {
            $report = (new WorkbookImporter)->import($path, Filament::getTenant()->getKey(), (bool) ($state['ignorer_erreurs'] ?? false));
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Fichier illisible')->body('Le classeur n\'a pas pu être lu : '.$e->getMessage())->danger()->persistent()->send();

            return;
        } finally {
            Storage::disk('local')->delete($state['fichier']);
        }

        $this->result = $this->present($report);
        $notification = Notification::make()->persistent();
        match (true) {
            $report->committed && ! $report->hasErrors() => $notification->title('Import terminé : '.$report->summary())->success(),
            $report->committed => $notification->title('Import partiel : '.$report->summary())->warning(),
            default => $notification->title('Import annulé : aucune donnée enregistrée')->body($report->errorCount().' ligne(s) en erreur — voir le détail ci-dessous.')->danger(),
        };
        $notification->send();
        $this->form->fill(['ignorer_erreurs' => $state['ignorer_erreurs'] ?? false]);
    }

    /** @return array<string, mixed> */
    private function present(WorkbookReport $report): array
    {
        return [
            'committed' => $report->committed,
            'summary' => $report->summary(),
            'missing' => $report->missing,
            'sheets' => collect($report->sheets)->map(fn ($s, $name) => [
                'name' => $name, 'created' => $s->created, 'updated' => $s->updated, 'errors' => count($s->errors),
            ])->values()->all(),
            'errors' => $report->errorLines(200),
        ];
    }
}
