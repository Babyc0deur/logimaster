<?php

namespace App\Filament\Widgets;

use App\Domain\Indicators\IndicatorCatalog;
use App\Filament\Pages\Dashboard;
use App\Support\IndicatorViewData;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Carte des 9 indicateurs DDKM : valeur du mois, évolution vs mois précédent, couleur selon l'objectif ; chaque carte ouvre son détail. */
class IndicatorCards extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    protected string $view = 'filament.widgets.indicator-cards';

    protected function getViewData(): array
    {
        $data = IndicatorViewData::get($this->pageFilters);
        $cards = [];
        foreach (IndicatorCatalog::all() as $key => $meta) {
            $row = $data['rows'][$key];
            $has = $row['districts'] > 0;
            $delta = $row['delta'];
            $cards[] = [
                'key' => $key,
                'label' => $meta['short'],
                'title' => $meta['label'],
                'icon' => $meta['icon'],
                'value' => $has ? IndicatorCatalog::format($key, $row['value']) : '—',
                'color' => $has ? IndicatorCatalog::color($key, $row['value']) : 'gray',
                'delta' => $has && $delta !== null ? sprintf('%+.1f %s', $delta, $meta['unit'] === '%' ? 'pt' : ($meta['unit'] === 'FCFA' ? 'FCFA' : $meta['unit'])) : null,
                'delta_good' => $delta === null || $meta['direction'] === null ? null : ($meta['direction'] === 'up' ? $delta >= 0 : $delta <= 0),
                'sub' => $this->subtitle($key, $row, $has),
                'definition' => $meta['definition'],
                'url' => Dashboard::getUrl(['filters' => array_filter(['indicateur' => $key] + ($this->pageFilters ?? []))]).'#indicateur-detail',
            ];
        }

        return ['cards' => $cards, 'month' => $data['month'], 'empty' => collect($data['rows'])->every(fn ($r) => $r['districts'] === 0)];
    }

    private function subtitle(string $key, array $row, bool $has): ?string
    {
        if (! $has) {
            return null;
        }
        $b = $row['breakdown'];
        $n = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');

        return match ($key) {
            'respect_chronogramme' => $n($b['numerator'] ?? 0).'/'.$n($b['denominator'] ?? 0).' sites livrés',
            'taux_immobilisation' => $n($b['numerator'] ?? 0).'/'.$n($b['denominator'] ?? 0).' jours',
            'utilisation_vehicules' => $n($b['numerator'] ?? 0).'/'.$n($b['denominator'] ?? 0).' jours',
            'cout_global' => ($b['km'] ?? 0) > 0 ? $n($row['value'] / $b['km']).' FCFA/km' : null,
            'respect_circuits' => $n($b['numerator'] ?? 0).'/'.$n($b['denominator'] ?? 0).' circuits',
            'respect_espc' => $n($b['numerator'] ?? 0).'/'.$n($b['denominator'] ?? 0).' livraisons',
            'distance_totale' => $n($b['nb_sorties'] ?? 0).' sorties',
            default => null,
        };
    }
}
