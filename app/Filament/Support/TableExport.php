<?php

namespace App\Filament\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exports Excel (.xlsx) et PDF du tableau tel qu'affiché (filtres, recherche et tri appliqués).
 * À utiliser dans getHeaderActions() d'une page ListRecords : TableExport::actions($this, 'vehicules').
 */
class TableExport
{
    public const LIMIT = 5000;

    /** @return array<int, Action> */
    public static function actions(string $filename, string $title): array
    {
        return [
            Action::make('export_xlsx')
                ->label('Excel')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn ($livewire) => self::xlsx($livewire, $filename)),
            Action::make('export_pdf')
                ->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                ->action(fn ($livewire) => self::pdf($livewire, $filename, $title)),
        ];
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, string>>} en-têtes et lignes */
    public static function rows($livewire): array
    {
        $columns = collect($livewire->getTable()->getColumns())
            ->reject(fn ($c) => $c->isToggledHiddenByDefault() || $c->isHidden())->values();
        $headers = $columns->map(fn ($c) => (string) $c->getLabel())->all();

        $rows = [];
        foreach ($livewire->getFilteredSortedTableQuery()->limit(self::LIMIT)->get() as $record) {
            $rows[] = $columns->map(function ($column) use ($record) {
                $column->record($record);
                $state = $column->formatState($column->getState());

                return is_array($state) ? implode(', ', $state) : trim(strip_tags((string) $state));
            })->all();
        }

        return [$headers, $rows];
    }

    public static function xlsx($livewire, string $filename)
    {
        [$headers, $rows] = self::rows($livewire);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return response()->download($path, $filename.'-'.now()->format('Ymd').'.xlsx')->deleteFileAfterSend();
    }

    public static function pdf($livewire, string $filename, string $title)
    {
        [$headers, $rows] = self::rows($livewire);
        $pdf = Pdf::loadView('exports.table', ['title' => $title, 'headers' => $headers, 'rows' => $rows])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), $filename.'-'.now()->format('Ymd').'.pdf');
    }
}
