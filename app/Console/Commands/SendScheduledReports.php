<?php

namespace App\Console\Commands;

use App\Domain\Reports\ReportService;
use Illuminate\Console\Command;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send {--monthly : Envoie aussi le rapport mensuel DDKM automatique aux superviseurs (à lancer le 1er du mois)}';

    protected $description = 'Envoie par email les rapports planifiés du jour';

    public function handle(ReportService $service): int
    {
        $n = $service->runSchedules();
        $this->info("{$n} envoi(s) planifié(s) traité(s).");

        if ($this->option('monthly')) {
            $m = $service->sendMonthlyToSupervisors();
            $this->info("{$m} rapport(s) mensuel(s) DDKM envoyé(s) aux superviseurs.");
        }

        return self::SUCCESS;
    }
}
