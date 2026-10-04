<?php

namespace App\Console\Commands;

use App\Jobs\ComputeDistrictIndicators;
use App\Models\District;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ComputeIndicators extends Command
{
    protected $signature = 'indicators:compute
        {--period= : Mois à calculer (YYYY-MM), défaut : mois courant}
        {--district= : Limiter à un district (UUID)}
        {--queue : Mettre les calculs en file (queue "reports") au lieu de les exécuter immédiatement}';

    protected $description = 'Calcule les snapshots des 9 indicateurs DDKM';

    public function handle(): int
    {
        $month = $this->option('period')
            ? CarbonImmutable::createFromFormat('Y-m', $this->option('period'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $districts = District::query()
            ->when($this->option('district'), fn ($q, $id) => $q->whereKey($id))
            ->pluck('id');

        foreach ($districts as $id) {
            $job = new ComputeDistrictIndicators($id, $month->toDateString());
            $this->option('queue') ? dispatch($job) : dispatch_sync($job);
        }

        $this->info("Indicateurs {$month->format('Y-m')} : {$districts->count()} district(s) ".($this->option('queue') ? 'mis en file.' : 'calculés.'));

        return self::SUCCESS;
    }
}
