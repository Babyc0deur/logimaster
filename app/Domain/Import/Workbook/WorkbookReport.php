<?php

namespace App\Domain\Import\Workbook;

use App\Domain\Import\ImportReport;

/** Résultat de l'import du classeur : un rapport par onglet. */
final class WorkbookReport
{
    /** @var array<string, ImportReport> */
    public array $sheets = [];

    /** @var array<int, string> onglets absents du fichier */
    public array $missing = [];

    public bool $committed = false;

    public function sheet(string $name): ImportReport
    {
        return $this->sheets[$name] ??= new ImportReport;
    }

    public function created(): int
    {
        return array_sum(array_map(fn (ImportReport $r) => $r->created, $this->sheets));
    }

    public function updated(): int
    {
        return array_sum(array_map(fn (ImportReport $r) => $r->updated, $this->sheets));
    }

    public function errorCount(): int
    {
        return array_sum(array_map(fn (ImportReport $r) => count($r->errors), $this->sheets));
    }

    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    /** @return array<int, string> « ONGLET, ligne N : message » */
    public function errorLines(int $limit = 0): array
    {
        $lines = [];
        foreach ($this->sheets as $name => $report) {
            foreach ($report->errors as $line => $messages) {
                $lines[] = "{$name}, ligne {$line} : ".implode(' ; ', $messages);
            }
        }

        return $limit > 0 ? array_slice($lines, 0, $limit) : $lines;
    }

    public function summary(): string
    {
        $text = "{$this->created()} créé(s), {$this->updated()} mis à jour";

        return $this->hasErrors() ? $text.", {$this->errorCount()} ligne(s) en erreur" : $text;
    }

    public function toArray(): array
    {
        return [
            'committed' => $this->committed, 'created' => $this->created(), 'updated' => $this->updated(),
            'error_count' => $this->errorCount(), 'missing_sheets' => $this->missing,
            'sheets' => array_map(fn (ImportReport $r) => ['created' => $r->created, 'updated' => $r->updated, 'errors' => $r->errors], $this->sheets),
        ];
    }
}
