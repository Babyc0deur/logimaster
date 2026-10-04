<?php

namespace App\Domain\Import;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Description d'un jeu de données importable/exportable : colonnes du modèle Excel,
 * règles de validation, et création/mise à jour d'une ligne.
 *
 * Colonne : name (en-tête du fichier), label, required, type (string|int|decimal|date|time|bool),
 * example, help, values (clé => libellé : valeurs autorisées, saisie libre insensible à la casse/accents).
 */
abstract class ImportDefinition
{
    /** Identifiant d'URL / de registre (ex. « vehicles »). */
    abstract public function key(): string;

    abstract public function label(): string;

    /** Module de permission (create_{module}, view_{module}). */
    abstract public function module(): string;

    /** @return array<int, array<string, mixed>> */
    abstract public function columns(): array;

    /** Règles Laravel sur la ligne normalisée. */
    abstract public function rules(string $districtId): array;

    /** Crée ou met à jour. Retourne « created » ou « updated ». @throws ImportException */
    abstract public function save(array $row, string $districtId): string;

    /** Ligne d'export (mêmes colonnes que le modèle d'import). */
    abstract public function toRow(Model $model): array;

    abstract public function exportQuery(string $districtId): Builder;

    /** Texte d'aide additionnel affiché dans l'onglet « Aide » du modèle. */
    public function notes(): array
    {
        return [];
    }

    /** @return array<int, string> */
    public function headers(): array
    {
        return array_column($this->columns(), 'name');
    }

    /** Ne conserve que les valeurs renseignées (une cellule vide ne réécrase pas la donnée existante). */
    protected function filled(array $row): array
    {
        return array_filter($row, fn ($v) => $v !== null && $v !== '');
    }
}
