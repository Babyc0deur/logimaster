<?php

namespace App\Filament\Support;

use App\Domain\Import\ImportException;
use App\Domain\Import\ImportRegistry;
use App\Domain\Import\SpreadsheetImporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Menu « Import / Export » d'une liste : modèle d'import, import, export au format d'import,
 * plus l'export du tableau filtré en Excel/PDF.
 */
class ImportExportActions
{
    public static function group(string $key, string $filename, string $title, bool $withTableExport = true): ActionGroup
    {
        $def = ImportRegistry::get($key);
        $importer = new SpreadsheetImporter;

        $actions = [
            Action::make('modele_import')
                ->label("Télécharger le modèle d'import")->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => response()->download($importer->template($def), "modele-import-{$filename}.xlsx")->deleteFileAfterSend()),

            Action::make('importer')
                ->label('Importer un fichier')->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => auth()->user()->can("create_{$def->module()}"))
                ->modalHeading("Importer — {$def->label()}")
                ->modalDescription("Fichier Excel (.xlsx) ou CSV basé sur le modèle d'import. Les lignes sont ajoutées ou mises à jour dans le district courant.")
                ->modalSubmitActionLabel('Importer')
                ->schema([
                    FileUpload::make('fichier')->label('Fichier')->required()->disk('local')->directory('imports')->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'text/plain',
                            'application/csv', 'application/vnd.ms-excel',
                        ])->maxSize(10240),
                    Toggle::make('ignorer_erreurs')->label('Ignorer les lignes en erreur')
                        ->helperText('Désactivé : rien n\'est importé si une ligne est invalide (tout ou rien).'),
                ])
                ->action(function (array $data) use ($def, $importer) {
                    $path = Storage::disk('local')->path($data['fichier']);
                    try {
                        $report = $importer->import($def, $path, Filament::getTenant()->getKey(), (bool) ($data['ignorer_erreurs'] ?? false));
                    } catch (ImportException $e) {
                        Notification::make()->title('Fichier invalide')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['fichier']);
                    }

                    $errors = $report->errorLines(8);
                    $more = count($report->errors) - count($errors);
                    $body = $errors ? implode("\n", $errors).($more > 0 ? "\n… et {$more} autre(s) ligne(s)" : '') : null;
                    $notification = Notification::make()->persistent();
                    if ($report->committed && ! $report->hasErrors()) {
                        $notification->title('Import terminé : '.$report->summary())->success();
                    } elseif ($report->committed) {
                        $notification->title('Import partiel : '.$report->summary())->body($body)->warning();
                    } else {
                        $notification->title('Import annulé : aucune donnée enregistrée')->body($body)->danger();
                    }
                    $notification->send();
                }),

            Action::make('export_import')
                ->label("Exporter (format d'import)")->icon('heroicon-o-arrow-up-on-square')
                ->action(fn () => response()->download($importer->export($def, Filament::getTenant()->getKey()), "{$filename}-donnees.xlsx")->deleteFileAfterSend()),
        ];

        if ($withTableExport) {
            array_push($actions, ...TableExport::actions($filename, $title));
        }

        return ActionGroup::make($actions)->label('Import / Export')->icon('heroicon-o-arrows-up-down')->button()->color('gray');
    }
}
