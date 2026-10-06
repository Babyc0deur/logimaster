<?php

namespace App\Domain\Reports;

/**
 * Graphiques des rapports, dessinés en SVG simple (rectangles, lignes, chemins, texte) pour que DomPDF les rende
 * à l'identique du navigateur. Tous les graphiques font 720 × (hauteur variable) et sont insérés pleine largeur.
 */
final class ChartSvg
{
    public const PALETTE = ['#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b', '#06b6d4', '#84cc16'];

    private const W = 720;

    private const FONT = 'font-family="DejaVu Sans, sans-serif"';

    /** Nombre au format français, compact au-dessus de 10 000 (12,5 k). */
    public static function num(float|int|null $n, int $dec = 0): string
    {
        $n = (float) $n;
        if (abs($n) >= 10000) {
            return number_format($n / 1000, 1, ',', ' ').' k';
        }
        if (abs($n - round($n)) < 0.005) {   // valeur entière : pas de « ,0 »
            $dec = 0;
        }

        return number_format($n, $dec, ',', ' ');
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function cut(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }

    /** Plafond « rond » de l'axe : 1, 2, 2,5, 5 ou 10 × 10^n. */
    private static function niceMax(float $v): float
    {
        if ($v <= 0) {
            return 1;
        }
        $pow = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $pow) {
                return $m * $pow;
            }
        }

        return 10 * $pow;
    }

    private static function open(int $h): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.self::W.'" height="'.$h.'" viewBox="0 0 '.self::W.' '.$h.'">'
            .'<rect width="'.self::W.'" height="'.$h.'" fill="#ffffff"/>';
    }

    /** Axe vertical : graduations, lignes de repère. @return array{string, callable} */
    private static function yAxis(float $max, float $x0, float $x1, float $y0, float $y1, string $unit): array
    {
        $svg = '';
        for ($i = 0; $i <= 4; $i++) {
            $v = $max * $i / 4;
            $y = $y1 - ($y1 - $y0) * $i / 4;
            $svg .= '<line x1="'.$x0.'" y1="'.round($y, 1).'" x2="'.$x1.'" y2="'.round($y, 1).'" stroke="#e5e7eb" stroke-width="1"/>'
                .'<text x="'.($x0 - 6).'" y="'.round($y + 3.5, 1).'" font-size="10" fill="#6b7280" text-anchor="end" '.self::FONT.'>'.self::e(self::num($v, $max < 10 ? 1 : 0)).'</text>';
        }
        if ($unit !== '') {
            $svg .= '<text x="'.($x0 - 6).'" y="'.($y0 - 10).'" font-size="10" fill="#6b7280" text-anchor="end" '.self::FONT.'>'.self::e($unit).'</text>';
        }

        return [$svg, fn (float $v) => $y1 - ($y1 - $y0) * min($v, $max) / $max];
    }

    private static function legend(array $series, float $xRight, float $y): string
    {
        $svg = '';
        $x = $xRight;
        foreach (array_reverse($series, true) as $i => $s) {
            $label = self::cut($s['name'], 22);
            $w = 22 + mb_strlen($label) * 6.2;
            $x -= $w;
            $svg .= '<rect x="'.round($x, 1).'" y="'.($y - 8).'" width="10" height="10" rx="2" fill="'.($s['color'] ?? self::PALETTE[$i % 8]).'"/>'
                .'<text x="'.round($x + 15, 1).'" y="'.$y.'" font-size="10" fill="#374151" '.self::FONT.'>'.self::e($label).'</text>';
        }

        return $svg;
    }

    /**
     * Barres verticales groupées.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array{name: string, values: array<int, float|int|null>, color?: string}>  $series
     */
    public static function bars(array $labels, array $series, string $unit = '', ?float $target = null, int $height = 230): string
    {
        $n = max(1, count($labels));
        $all = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        $max = self::niceMax(max($all->max() ?? 0, $target ?? 0));
        [$x0, $x1, $y0, $y1] = [58, self::W - 16, 34, $height - 36];
        [$axis, $y] = self::yAxis($max, $x0, $x1, $y0, $y1, $unit);

        $svg = self::open($height).$axis;
        $group = ($x1 - $x0) / $n;
        $k = max(1, count($series));
        $bw = min(46, $group * 0.72 / $k);
        foreach ($labels as $i => $label) {
            $gx = $x0 + $group * $i + ($group - $bw * $k) / 2;
            foreach ($series as $j => $s) {
                $v = $s['values'][$i] ?? null;
                if ($v === null) {
                    continue;
                }
                $top = $y((float) $v);
                $svg .= '<rect x="'.round($gx + $bw * $j, 1).'" y="'.round($top, 1).'" width="'.round($bw - 2, 1).'" height="'.round(max(0, $y1 - $top), 1).'" rx="2" fill="'.($s['color'] ?? self::PALETTE[$j % 8]).'"/>';
                if ($n <= 8 && $k === 1) {
                    $svg .= '<text x="'.round($gx + $bw * $j + ($bw - 2) / 2, 1).'" y="'.round($top - 4, 1).'" font-size="10" fill="#111827" text-anchor="middle" '.self::FONT.'>'.self::e(self::num((float) $v, (float) $v < 10 ? 1 : 0)).'</text>';
                }
            }
            $svg .= '<text x="'.round($x0 + $group * ($i + .5), 1).'" y="'.($y1 + 16).'" font-size="10" fill="#374151" text-anchor="middle" '.self::FONT.'>'.self::e(self::cut($label, (int) max(6, $group / 6.2))).'</text>';
        }
        if ($target !== null) {
            $ty = round($y($target), 1);
            $svg .= '<line x1="'.$x0.'" y1="'.$ty.'" x2="'.$x1.'" y2="'.$ty.'" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="6,4"/>'
                .'<text x="'.$x1.'" y="'.($ty - 4).'" font-size="10" fill="#ef4444" text-anchor="end" '.self::FONT.'>Objectif '.self::e(self::num($target, 0)).'</text>';
        }
        if (count($series) > 1) {
            $svg .= self::legend($series, self::W - 16, 16);
        }

        return $svg.'</svg>';
    }

    /**
     * Barres horizontales (classement) ; une couleur par barre possible.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float|int>  $values
     * @param  array<int, string>  $colors
     */
    public static function hbars(array $labels, array $values, string $unit = '', array $colors = [], ?float $target = null): string
    {
        $rows = count($labels);
        $rowH = 24;
        $h = 26 + $rows * $rowH + 14;
        [$x0, $x1] = [200, self::W - 70];
        $max = self::niceMax(max(collect($values)->map(fn ($v) => (float) $v)->max() ?? 0, $target ?? 0));
        $svg = self::open($h);
        foreach ($labels as $i => $label) {
            $y = 18 + $i * $rowH;
            $v = (float) ($values[$i] ?? 0);
            $w = ($x1 - $x0) * min($v, $max) / $max;
            $svg .= '<text x="'.($x0 - 8).'" y="'.($y + 14).'" font-size="11" fill="#374151" text-anchor="end" '.self::FONT.'>'.self::e(self::cut($label, 34)).'</text>'
                .'<rect x="'.$x0.'" y="'.$y.'" width="'.($x1 - $x0).'" height="16" rx="3" fill="#f3f4f6"/>'
                .'<rect x="'.$x0.'" y="'.$y.'" width="'.round(max(0, $w), 1).'" height="16" rx="3" fill="'.($colors[$i] ?? self::PALETTE[0]).'"/>'
                .'<text x="'.($x1 + 8).'" y="'.($y + 12.5).'" font-size="11" font-weight="bold" fill="#111827" '.self::FONT.'>'.self::e(self::num($v, $v < 10 ? 1 : 0).($unit ? ' '.$unit : '')).'</text>';
        }
        if ($target !== null) {
            $tx = round($x0 + ($x1 - $x0) * $target / $max, 1);
            $svg .= '<line x1="'.$tx.'" y1="10" x2="'.$tx.'" y2="'.($h - 10).'" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="5,4"/>';
        }

        return $svg.'</svg>';
    }

    /**
     * Barres horizontales empilées : une barre par ligne (véhicule), un segment par série (motif), total en bout de barre.
     *
     * @param  array<string, array<string, float|int>>  $matrix  ligne => [série => valeur]
     * @param  array<int, string>  $series  ordre et couleurs des séries
     */
    public static function hstack(array $matrix, array $series, string $unit = ''): string
    {
        $rows = count($matrix);
        $rowH = 24;
        $legendH = 26;
        $h = $legendH + 14 + $rows * $rowH + 10;
        [$x0, $x1] = [150, self::W - 80];
        $max = self::niceMax(max(array_map(fn ($r) => array_sum($r), $matrix) ?: [0]));
        $svg = self::open($h);
        $svg .= self::legend(array_map(fn ($s, $i) => ['name' => $s, 'color' => self::PALETTE[$i % 8]], $series, array_keys($series)), self::W - 16, 16);
        $i = 0;
        foreach ($matrix as $label => $values) {
            $y = $legendH + 10 + $i * $rowH;
            $x = $x0;
            $svg .= '<text x="'.($x0 - 8).'" y="'.($y + 13).'" font-size="11" fill="#374151" text-anchor="end" '.self::FONT.'>'.self::e(self::cut((string) $label, 22)).'</text>'
                .'<rect x="'.$x0.'" y="'.$y.'" width="'.($x1 - $x0).'" height="16" rx="3" fill="#f3f4f6"/>';
            foreach ($series as $k => $name) {
                $v = (float) ($values[$name] ?? 0);
                if ($v <= 0) {
                    continue;
                }
                $w = ($x1 - $x0) * $v / $max;
                $svg .= '<rect x="'.round($x, 1).'" y="'.$y.'" width="'.round(max(1, $w), 1).'" height="16" fill="'.self::PALETTE[$k % 8].'"/>';
                $x += $w;
            }
            $total = array_sum($values);
            $svg .= '<text x="'.round(min($x + 6, $x1 + 6), 1).'" y="'.($y + 12.5).'" font-size="11" font-weight="bold" fill="#111827" '.self::FONT.'>'.self::e(self::num($total, $total < 10 ? 1 : 0).($unit ? ' '.$unit : '')).'</text>';
            $i++;
        }

        return $svg.'</svg>';
    }

    /**
     * Courbes (évolution dans le temps).
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array{name: string, values: array<int, float|int|null>, color?: string}>  $series
     */
    public static function line(array $labels, array $series, string $unit = '', ?float $target = null, int $height = 220): string
    {
        $n = count($labels);
        $all = collect($series)->flatMap(fn ($s) => $s['values'])->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        $max = self::niceMax(max($all->max() ?? 0, $target ?? 0));
        [$x0, $x1, $y0, $y1] = [58, self::W - 20, 34, $height - 36];
        [$axis, $y] = self::yAxis($max, $x0, $x1, $y0, $y1, $unit);
        $px = fn (int $i) => $n > 1 ? $x0 + 14 + ($x1 - $x0 - 28) * $i / ($n - 1) : ($x0 + $x1) / 2;

        $svg = self::open($height).$axis;
        foreach ($labels as $i => $label) {
            if ($n > 8 && $i % 2 === 1 && $i !== $n - 1) {
                continue;
            }
            $svg .= '<text x="'.round($px($i), 1).'" y="'.($y1 + 16).'" font-size="10" fill="#374151" text-anchor="middle" '.self::FONT.'>'.self::e(self::cut($label, 9)).'</text>';
        }
        if ($target !== null) {
            $ty = round($y($target), 1);
            $svg .= '<line x1="'.$x0.'" y1="'.$ty.'" x2="'.$x1.'" y2="'.$ty.'" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="6,4"/>'
                .'<text x="'.$x1.'" y="'.($ty - 4).'" font-size="10" fill="#ef4444" text-anchor="end" '.self::FONT.'>Objectif '.self::e(self::num($target, 0)).'</text>';
        }
        foreach ($series as $j => $s) {
            $color = $s['color'] ?? self::PALETTE[$j % 8];
            $pts = [];
            foreach ($s['values'] as $i => $v) {
                $v !== null && $pts[] = [round($px($i), 1), round($y((float) $v), 1), (float) $v];
            }
            if (count($pts) > 1) {
                $svg .= '<polyline points="'.implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $pts)).'" fill="none" stroke="'.$color.'" stroke-width="2.5" stroke-linejoin="round"/>';
            }
            foreach ($pts as $k => [$cx, $cy, $v]) {
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="3.5" fill="#ffffff" stroke="'.$color.'" stroke-width="2"/>';
                if ($j === 0 && ($k === count($pts) - 1 || count($pts) <= 6)) {
                    $svg .= '<text x="'.$cx.'" y="'.($cy - 8).'" font-size="10" fill="#111827" text-anchor="middle" '.self::FONT.'>'.self::e(self::num($v, $v < 10 ? 1 : 0)).'</text>';
                }
            }
        }
        if (count($series) > 1) {
            $svg .= self::legend($series, self::W - 16, 16);
        }

        return $svg.'</svg>';
    }

    /**
     * Anneau avec légende (répartition).
     *
     * @param  array<int, string>  $labels
     * @param  array<int, float|int>  $values
     */
    public static function donut(array $labels, array $values, string $unit = '', array $colors = []): string
    {
        $total = (float) array_sum($values);
        $h = max(190, 40 + count($labels) * 24);
        [$cx, $cy, $r, $r2] = [130, $h / 2, 76, 46];
        $svg = self::open($h);

        if ($total <= 0) {
            $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.(($r + $r2) / 2).'" fill="none" stroke="#e5e7eb" stroke-width="'.($r - $r2).'"/>'
                .'<text x="'.$cx.'" y="'.($cy + 4).'" font-size="11" fill="#6b7280" text-anchor="middle" '.self::FONT.'>Aucune donnée</text>';

            return $svg.'</svg>';
        }
        $angle = -M_PI / 2;
        foreach ($values as $i => $v) {
            if ($v <= 0) {
                continue;
            }
            $sweep = min($v / $total, 0.9999) * 2 * M_PI;
            [$a1, $a2] = [$angle, $angle + $sweep];
            $large = $sweep > M_PI ? 1 : 0;
            $p = fn (float $rad, float $a) => round($cx + $rad * cos($a), 2).' '.round($cy + $rad * sin($a), 2);
            $svg .= '<path d="M '.$p($r, $a1).' A '.$r.' '.$r.' 0 '.$large.' 1 '.$p($r, $a2).' L '.$p($r2, $a2).' A '.$r2.' '.$r2.' 0 '.$large.' 0 '.$p($r2, $a1).' Z" fill="'.($colors[$i] ?? self::PALETTE[$i % 8]).'" stroke="#ffffff" stroke-width="1.5"/>';
            $angle = $a2;
        }
        $svg .= '<text x="'.$cx.'" y="'.($cy + 2).'" font-size="17" font-weight="bold" fill="#111827" text-anchor="middle" '.self::FONT.'>'.self::e(self::num($total, $total < 10 ? 1 : 0)).'</text>'
            .'<text x="'.$cx.'" y="'.($cy + 17).'" font-size="10" fill="#6b7280" text-anchor="middle" '.self::FONT.'>'.self::e($unit ?: 'total').'</text>';

        $ly = ($h - count($labels) * 24) / 2 + 14;
        foreach ($labels as $i => $label) {
            $y = $ly + $i * 24;
            $v = (float) ($values[$i] ?? 0);
            $svg .= '<rect x="270" y="'.($y - 10).'" width="12" height="12" rx="2" fill="'.($colors[$i] ?? self::PALETTE[$i % 8]).'"/>'
                .'<text x="290" y="'.$y.'" font-size="11" fill="#374151" '.self::FONT.'>'.self::e(self::cut($label, 34)).'</text>'
                .'<text x="'.(self::W - 20).'" y="'.$y.'" font-size="11" font-weight="bold" fill="#111827" text-anchor="end" '.self::FONT.'>'.self::e(self::num($v, $v < 10 ? 1 : 0).($unit ? ' '.$unit : '').'  ·  '.number_format($v / $total * 100, 0).' %').'</text>';
        }

        return $svg.'</svg>';
    }
}
