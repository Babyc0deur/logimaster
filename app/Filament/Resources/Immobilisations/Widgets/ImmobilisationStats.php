<?php

namespace App\Filament\Resources\Immobilisations\Widgets;

use App\Domain\Indicators\Calculators\TauxImmobilisationCalculator;
use App\Models\Immobilisation;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ImmobilisationStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $districtId = Filament::getTenant()->getKey();
        $now = CarbonImmutable::now();
        $taux = (new TauxImmobilisationCalculator)->compute($districtId, $now->startOfMonth(), $now->endOfMonth());

        $rows = Immobilisation::where('district_id', $districtId)->get();
        $finished = $rows->where('statut', 'terminee');
        $avg = $finished->isNotEmpty() ? round($finished->avg('duree_jours'), 1) : null;

        return [
            Stat::make("Taux d'immobilisation (mois)", $taux->value.' %')
                ->description(number_format($taux->breakdown['numerator'], 0).' jours sur '.number_format($taux->breakdown['denominator'], 0).' jours-véhicule')
                ->color($taux->value > 10 ? 'danger' : 'success')->icon('heroicon-o-clock'),
            Stat::make('Coûts de réparation', number_format((float) $rows->sum('montant'), 0, ',', ' ').' FCFA')
                ->description($rows->count().' intervention(s) enregistrée(s)')->icon('heroicon-o-wrench-screwdriver')->color('warning'),
            Stat::make('Durée moyenne', $avg !== null ? "{$avg} jours" : '—')
                ->description('Sur les immobilisations terminées')->icon('heroicon-o-calendar-days'),
            Stat::make('En cours', $rows->where('statut', '!=', 'terminee')->count())
                ->description($rows->where('statut', 'en_attente_pieces')->count().' en attente de pièces')->icon('heroicon-o-exclamation-triangle')->color('danger'),
        ];
    }
}
