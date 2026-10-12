<?php

namespace App\Console\Commands;

use App\Domain\Indicators\IndicatorService;
use App\Models\IndicatorSnapshot;
use App\Support\DashboardFilters;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calcule les instantanés d'indicateurs manquants de la période par défaut, pour les seuls districts qui ont de l'activité
 * dans le mois (un district sans saisie n'a rien à calculer). Rapide, et sans effet quand tout est déjà calculé : prévu au démarrage,
 * car les instantanés ne font pas partie de l'export de données.
 */
class BackfillIndicators extends Command
{
    protected $signature = 'indicators:backfill';

    protected $description = 'Calcule les indicateurs manquants des districts qui ont de l\'activité sur la période par défaut';

    private const ACTIVITY = [
        'sorties_vehicules' => 'date_sortie', 'ravitaillements' => 'date_ravitaillement', 'chronogrammes' => 'date_prevue',
        'immobilisations' => 'date_debut', 'vidanges' => 'date', 'expenses' => 'date_depense',
    ];

    public function handle(IndicatorService $service): int
    {
        if (! Schema::hasTable('indicator_snapshots') || ! Schema::hasTable('sorties_vehicules')) {
            return self::SUCCESS;
        }
        $from = DashboardFilters::defaultFrom()->startOfMonth();
        $until = DashboardFilters::defaultUntil()->startOfMonth();
        $done = 0;
        for ($m = $from; $m <= $until; $m = $m->addMonthNoOverflow()) {
            $start = $m->toDateString();
            $end = $m->endOfMonth()->toDateString();
            $active = collect();
            foreach (self::ACTIVITY as $table => $col) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                    $active = $active->merge(DB::table($table)->whereBetween($col, [$start, $end])->distinct()->pluck('district_id'));
                }
            }
            $have = IndicatorSnapshot::where('period', $start)->distinct()->pluck('district_id');
            foreach ($active->unique()->diff($have) as $districtId) {
                $service->computeForDistrict($districtId, CarbonImmutable::parse($start));
                $done++;
            }
        }
        $this->info("Indicateurs : {$done} district(s)-mois calculé(s).");

        return self::SUCCESS;
    }
}
