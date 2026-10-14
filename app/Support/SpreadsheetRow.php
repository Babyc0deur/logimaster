<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;

/**
 * Ligne d'export Excel sans injection de formules (OWASP A05) : OpenSpout transforme en formule tout texte qui commence
 * par « = ». Les textes saisis par les utilisateurs (raisons, signalements, noms) sont donc toujours écrits comme du texte.
 */
class SpreadsheetRow
{
    /** @param  array<int, mixed>  $values */
    public static function from(array $values): Row
    {
        return new Row(array_map(
            fn ($v) => is_string($v) ? new StringCell($v, null) : Cell::fromValue($v),
            array_values($values),
        ));
    }
}
