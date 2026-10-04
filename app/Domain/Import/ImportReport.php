<?php

namespace App\Domain\Import;

final class ImportReport
{
    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, array<int, string>> numéro de ligne (tableur) => messages */
    public array $errors = [];

    public bool $committed = false;

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function addError(int $line, string $message): void
    {
        $this->errors[$line][] = $message;
    }

    /** @return array<int, string> messages « Ligne N : … » */
    public function errorLines(int $limit = 0): array
    {
        $lines = [];
        foreach ($this->errors as $line => $messages) {
            $lines[] = "Ligne {$line} : ".implode(' ; ', $messages);
        }

        return $limit > 0 ? array_slice($lines, 0, $limit) : $lines;
    }

    public function summary(): string
    {
        $text = "{$this->created} créé(s), {$this->updated} mis à jour";

        return $this->hasErrors() ? $text.', '.count($this->errors).' ligne(s) en erreur' : $text;
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created, 'updated' => $this->updated, 'committed' => $this->committed,
            'error_count' => count($this->errors), 'errors' => $this->errors,
        ];
    }
}
