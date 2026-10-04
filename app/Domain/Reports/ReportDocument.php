<?php

namespace App\Domain\Reports;

/**
 * Contenu d'un rapport, indépendant du format : titre, sous-titre, sections (chiffres clés, tableaux, texte).
 *
 * section = ['heading' => string, 'kpis' => [['label','value']], 'tables' => [['title','headers','rows']], 'text' => string[], 'charts' => [['title','svg']]]
 */
final class ReportDocument
{
    /** @var array<int, array{heading: string, kpis: array, tables: array, text: array, charts: array}> */
    public array $sections = [];

    public function __construct(public string $title, public string $subtitle, public string $scope, public string $period) {}

    /**
     * @param  array<int, array{label: string, value: string}>  $kpis
     * @param  array<int, array{title: string, headers: array<int, string>, rows: array<int, array<int, mixed>>}>  $tables
     * @param  array<int, string>  $text
     * @param  array<int, array{title: string, svg: string}>  $charts  graphiques SVG (voir ChartSvg), affichés dans le PDF
     */
    public function section(string $heading, array $kpis = [], array $tables = [], array $text = [], array $charts = []): self
    {
        $this->sections[] = ['heading' => $heading, 'kpis' => $kpis, 'tables' => $tables, 'text' => $text, 'charts' => $charts];

        return $this;
    }

    public function filename(string $ext): string
    {
        return \Illuminate\Support\Str::slug($this->title.' '.$this->scope.' '.$this->period).'.'.$ext;
    }
}
