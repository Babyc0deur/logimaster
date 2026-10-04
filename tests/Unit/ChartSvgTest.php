<?php

namespace Tests\Unit;

use App\Domain\Reports\ChartSvg;
use PHPUnit\Framework\TestCase;

/** Graphiques des rapports : SVG toujours bien formé, même sans données ou avec des valeurs nulles. */
class ChartSvgTest extends TestCase
{
    private function assertValidSvg(string $svg): void
    {
        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml, 'SVG mal formé');
        $this->assertSame('svg', $xml->getName());
    }

    public function test_every_chart_type_is_well_formed(): void
    {
        $this->assertValidSvg(ChartSvg::bars(['Mai', 'Juin'], [['name' => 'Litres', 'values' => [10, 25]]], 'L', 20));
        $this->assertValidSvg(ChartSvg::hbars(['A & B', 'C <D>'], [12.5, 80], '%', ['#22c55e', '#ef4444'], 90));
        $this->assertValidSvg(ChartSvg::line(['Mai', 'Juin', 'Juil'], [['name' => 'x', 'values' => [1, null, 3]]], '%', 50));
        $this->assertValidSvg(ChartSvg::donut(['Oui', 'Non'], [3, 1], 'sorties'));
    }

    public function test_empty_and_zero_data_do_not_break(): void
    {
        $this->assertValidSvg(ChartSvg::bars([], [['name' => 'x', 'values' => []]]));
        $this->assertValidSvg(ChartSvg::bars(['A'], [['name' => 'x', 'values' => [0]]]));
        $this->assertValidSvg(ChartSvg::hbars([], []));
        $this->assertValidSvg(ChartSvg::line(['A'], [['name' => 'x', 'values' => [5]]]));
        $this->assertValidSvg(ChartSvg::line([], [['name' => 'x', 'values' => []]]));
        $this->assertStringContainsString('Aucune donnée', ChartSvg::donut(['A'], [0]));
    }

    public function test_numbers_are_formatted_in_french_without_useless_decimals(): void
    {
        $this->assertSame('4', ChartSvg::num(4.0, 1));
        $this->assertSame('4,5', ChartSvg::num(4.5, 1));
        $this->assertSame('1 234', ChartSvg::num(1234));
        $this->assertSame('12,5 k', ChartSvg::num(12500));
    }
}
