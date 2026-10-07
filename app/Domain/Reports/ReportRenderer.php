<?php

namespace App\Domain\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Rend un ReportDocument en PDF (mise en page professionnelle) ou en classeur Excel (une feuille par section). */
class ReportRenderer
{
    public function pdf(ReportDocument $doc): string
    {
        return Pdf::loadView('reports.document', ['doc' => $doc, 'generatedAt' => now()])->setPaper('a4', 'portrait')->output();
    }

    /** Écrit le classeur dans un fichier temporaire et retourne son chemin. */
    public function xlsx(ReportDocument $doc): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('2563EB');
        $title = (new Style)->setFontBold()->setFontSize(14);

        // Feuille 1 : en-tête et chiffres clés de toutes les sections
        $writer->getCurrentSheet()->setName('Synthèse');
        $writer->addRow(Row::fromValues(['LogiMaster — '.$doc->title], $title));
        $writer->addRow(Row::fromValues([$doc->subtitle]));
        $writer->addRow(Row::fromValues(['Généré le '.now()->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues([]));
        foreach ($doc->sections as $section) {
            if (! $section['kpis'] && ! $section['text']) {
                continue;
            }
            $writer->addRow(Row::fromValues([$section['heading']], $bold));
            foreach ($section['kpis'] as $k) {
                $writer->addRow(Row::fromValues(['', $k['label'], $k['value']]));
            }
            foreach ($section['text'] as $line) {
                $writer->addRow(Row::fromValues(['', $line]));
            }
            $writer->addRow(Row::fromValues([]));
        }

        // Une feuille par section contenant des tableaux
        $used = ['synthese'];
        foreach ($doc->sections as $section) {
            if (! $section['tables']) {
                continue;
            }
            $name = $this->sheetName($section['heading'], $used);
            $writer->addNewSheetAndMakeItCurrent()->setName($name);
            $writer->addRow(Row::fromValues([$section['heading']], $title));
            $writer->addRow(Row::fromValues([]));
            foreach ($section['tables'] as $table) {
                $writer->addRow(Row::fromValues([$table['title']], $bold));
                $writer->addRow(Row::fromValues($table['headers'], $head));
                foreach ($table['rows'] as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn ($c) => is_scalar($c) || $c === null ? $c : (string) $c, $row)));
                }
                $writer->addRow(Row::fromValues([]));
            }
        }
        $writer->close();

        return $path;
    }

    /** Nom de feuille Excel valide (≤ 31 caractères, sans caractères interdits, unique). */
    private function sheetName(string $heading, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $heading)), 0, 28) ?: 'Feuille';
        $name = $base;
        $i = 2;
        while (in_array(mb_strtolower($name), $used, true)) {
            $name = mb_substr($base, 0, 26).' '.$i++;
        }
        $used[] = mb_strtolower($name);

        return $name;
    }
}
