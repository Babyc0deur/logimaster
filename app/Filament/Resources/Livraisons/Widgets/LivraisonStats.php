<?php

namespace App\Filament\Resources\Livraisons\Widgets;

use App\Domain\Indicators\Deliveries;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Consolidé du mois : % d'ESPC livrés dans les délais, retards, non livrés. */
class LivraisonStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $districtId = Filament::getTenant()->getKey();
        $now = CarbonImmutable::now();
        $plans = Deliveries::plans($districtId, $now->startOfMonth(), $now->endOfMonth());

        $planned = $onTime = $late24 = $lateMore = $notDelivered = $transit = 0;
        foreach ($plans as $plan) {
            foreach ($plan->livraisons as $l) {
                $planned++;
                if ($l->statut === 'livre') {
                    Deliveries::onSchedule($l, $plan) ? $onTime++ : ($l->retard_jours <= 1 ? $late24++ : $lateMore++);
                    $l->lieu_livraison === 'transit' && $transit++;
                } else {
                    $notDelivered++;
                }
            }
        }
        $pct = fn ($n) => $planned > 0 ? round($n / $planned * 100, 1).' %' : '—';

        return [
            Stat::make('Livrés dans les délais', $pct($onTime))->description("{$onTime} / {$planned} livraisons prévues ce mois")->icon('heroicon-o-check-badge')
                ->color($planned === 0 ? 'gray' : ($onTime / $planned >= 0.9 ? 'success' : ($onTime / $planned >= 0.75 ? 'warning' : 'danger'))),
            Stat::make('Retards', $late24 + $lateMore)->description("{$late24} de moins de 24 h · {$lateMore} de plus de 24 h")->icon('heroicon-o-clock')->color($late24 + $lateMore ? 'warning' : 'success'),
            Stat::make('Non livrés', $notDelivered)->description('Sites non livrés ou à traiter')->icon('heroicon-o-x-circle')->color($notDelivered ? 'danger' : 'success'),
            Stat::make('En point de transit', $transit)->description('Livrés en transit plutôt que sur site')->icon('heroicon-o-arrows-right-left')->color($transit ? 'warning' : 'success'),
        ];
    }
}
