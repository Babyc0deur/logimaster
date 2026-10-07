<?php

namespace App\Console\Commands;

use App\Domain\Organisation\PresMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Rattache les régions sanitaires à leur PRES (10 pôles régionaux). Sans effet quand tout est déjà en place. */
class SyncPres extends Command
{
    protected $signature = 'organisation:pres';

    protected $description = 'Rattache les 33 régions sanitaires à leurs 10 PRES';

    public function handle(): int
    {
        if (! Schema::hasTable('regions')) {
            return self::SUCCESS;
        }
        $r = PresMapping::apply();
        $this->info(sprintf('PRES : %d région(s) rattachée(s) à un nouveau PRES.', $r['moved']));
        if ($r['unknown']) {
            $this->warn('Régions absentes de la liste des PRES : '.implode(', ', $r['unknown']));
        }

        return self::SUCCESS;
    }
}
