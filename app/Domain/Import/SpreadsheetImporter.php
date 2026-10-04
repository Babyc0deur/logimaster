<?php

namespace App\Domain\Import;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;

/** Lecture, validation et import d'un fichier .xlsx / .csv ; génération du modèle et de l'export. */
class SpreadsheetImporter
{
    /**
     * @param  bool  $skipInvalid  true : importe les lignes valides et rapporte les autres ; false : tout ou rien.
     */
    public function import(ImportDefinition $def, string $path, string $districtId, bool $skipInvalid = false): ImportReport
    {
        $report = new ImportReport;
        $rows = $this->read($path, $def);

        if ($rows === []) {
            $report->addError(1, 'Le fichier ne contient aucune ligne de données.');

            return $report;
        }

        DB::beginTransaction();
        try {
            foreach ($rows as $line => $raw) {
                $row = $this->normalize($def, $raw);
                $validator = Validator::make($row, $def->rules($districtId), [], array_column($def->columns(), 'label', 'name'));
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $report->addError($line, $message);
                    }

                    continue;
                }
                try {
                    $result = DB::transaction(fn () => $def->save($row, $districtId));
                    $result === 'created' ? $report->created++ : $report->updated++;
                } catch (ImportException $e) {
                    $report->addError($line, $e->getMessage());
                }
            }

            if ($report->hasErrors() && ! $skipInvalid) {
                DB::rollBack();
                $report->created = $report->updated = 0;
            } else {
                DB::commit();
                $report->committed = true;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * Lit la feuille « Modèle » (ou la première) ; la ligne 1 contient les en-têtes.
     *
     * @return array<int, array<string, mixed>> numéro de ligne tableur => valeurs indexées par nom de colonne
     */
    public function read(string $path, ImportDefinition $def): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = $extension === 'csv' ? $this->csvReader($path) : new XlsxReader;
        $reader->open($path);

        $lookup = [];
        foreach ($def->columns() as $c) {
            $lookup[$this->slug($c['name'])] = $c['name'];
            $lookup[$this->slug($c['label'])] = $c['name'];
        }

        $rows = [];
        $chosen = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $chosen ??= $sheet;
            if (Str::lower($sheet->getName()) === 'modèle' || Str::lower($sheet->getName()) === 'modele') {
                $chosen = $sheet;
                break;
            }
        }

        if ($chosen) {
            $map = null;
            $line = 0;
            foreach ($chosen->getRowIterator() as $row) {
                $line++;
                $cells = array_map(fn ($c) => $c instanceof DateTimeInterface ? $c : (is_string($c) ? trim($c) : $c), $row->toArray());
                if ($map === null) {
                    $map = array_map(fn ($h) => $lookup[$this->slug((string) $h)] ?? null, $cells);
                    if (count(array_filter($map)) === 0) {
                        throw new ImportException('En-têtes non reconnus : utilisez le modèle d\'import (ligne 1).');
                    }

                    continue;
                }
                if (collect($cells)->every(fn ($c) => $c === null || $c === '')) {
                    continue;
                }
                $assoc = [];
                foreach ($map as $i => $name) {
                    if ($name !== null) {
                        $assoc[$name] = $cells[$i] ?? null;
                    }
                }
                $rows[$line] = $assoc;
            }
        }
        $reader->close();

        return $rows;
    }

    /** Convertit les cellules en valeurs typées (dates ISO, nombres, énumérations canoniques). */
    public function normalize(ImportDefinition $def, array $raw): array
    {
        $row = [];
        foreach ($def->columns() as $c) {
            $value = $raw[$c['name']] ?? null;
            if ($value === '' || $value === null) {
                $row[$c['name']] = null;

                continue;
            }
            $type = $c['type'] ?? 'string';
            if ($type === 'date') {
                $value = $this->toDate($value);
            } elseif ($type === 'time') {
                $value = $this->toTime($value);
            } elseif ($type === 'int' && is_numeric($value)) {
                $value = (int) round((float) $value);
            } elseif ($type === 'decimal' && is_string($value)) {
                $value = str_replace([' ', ','], ['', '.'], $value);
            } elseif ($type === 'bool') {
                $value = in_array(Str::lower((string) $value), ['1', 'oui', 'yes', 'true', 'vrai', 'x'], true);
            } elseif ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d');
            } elseif (is_float($value) && floor($value) === $value) {
                $value = (string) (int) $value; // 5.0 saisi pour un matricule → « 5 »
            } else {
                $value = is_scalar($value) ? (string) $value : $value;
            }
            if (isset($c['values']) && is_string($value)) {
                $value = $this->matchValue($c['values'], $value);
            }
            $row[$c['name']] = $value;
        }

        return $row;
    }

    /** Modèle d'import : onglet « Modèle » (en-têtes seuls), « Exemple » et « Aide ». Retourne le chemin du fichier. */
    public function template(ImportDefinition $def): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Modèle');
        $writer->addRow(Row::fromValues($def->headers()));

        $writer->addNewSheetAndMakeItCurrent()->setName('Exemple');
        $writer->addRow(Row::fromValues($def->headers()));
        $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['example'] ?? '', $def->columns())));

        $writer->addNewSheetAndMakeItCurrent()->setName('Aide');
        $writer->addRow(Row::fromValues(['Colonne', 'Obligatoire', 'Format', 'Valeurs autorisées / aide']));
        foreach ($def->columns() as $c) {
            $help = isset($c['values']) ? implode(' | ', array_map(fn ($k, $v) => $k === $v ? $k : "{$k} ({$v})", array_keys($c['values']), $c['values'])) : ($c['help'] ?? '');
            $writer->addRow(Row::fromValues([$c['name'], ($c['required'] ?? false) ? 'oui' : 'non', $c['type'] ?? 'texte', $help]));
        }
        foreach ($def->notes() as $note) {
            $writer->addRow(Row::fromValues(['', '', '', $note]));
        }
        $writer->close();

        return $path;
    }

    /** Export des données du district au format d'import (réimportable tel quel). */
    public function export(ImportDefinition $def, string $districtId): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Modèle');
        $writer->addRow(Row::fromValues($def->headers()));
        foreach ($def->exportQuery($districtId)->limit(20000)->get() as $model) {
            $row = $def->toRow($model);
            $writer->addRow(Row::fromValues(array_map(fn ($name) => $row[$name] ?? '', $def->headers())));
        }
        $writer->close();

        return $path;
    }

    private function csvReader(string $path): CsvReader
    {
        $first = (string) strtok((string) file_get_contents($path, false, null, 0, 4096), "\n");
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    public function toDate(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && $value > 20000 && $value < 80000) { // numéro de série Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }
        $value = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value; // la validation « date » signalera l'erreur
    }

    public function toTime(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        if (is_numeric($value) && $value >= 0 && $value < 1) { // fraction de jour Excel
            return gmdate('H:i', (int) round($value * 86400));
        }
        if (preg_match('/^(\d{1,2})[:h](\d{2})/', trim((string) $value), $m)) {
            return sprintf('%02d:%s', $m[1], $m[2]);
        }

        return $value;
    }

    /** @param array<string, string> $values */
    private function matchValue(array $values, string $input): string
    {
        $needle = $this->slug($input);
        foreach ($values as $key => $label) {
            if ($this->slug((string) $key) === $needle || $this->slug($label) === $needle) {
                return (string) $key;
            }
        }
        foreach (['gasoil' => 'diesel', 'gazole' => 'diesel', 'annulé' => 'annulee', 'annule' => 'annulee'] as $alias => $key) {
            if ($needle === $this->slug($alias) && isset($values[$key])) {
                return $key;
            }
        }

        return $input; // la règle « in » signalera la valeur inconnue
    }

    public function slug(string $text): string
    {
        return Str::of(Str::ascii($text))->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }
}
