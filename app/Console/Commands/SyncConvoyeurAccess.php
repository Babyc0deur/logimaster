<?php

namespace App\Console\Commands;

use App\Domain\Mobile\ConvoyeurAccess;
use App\Models\Personnel;
use Illuminate\Console\Command;

/** Crée les accès mobiles de tous les chefs de mission et passagers déjà enregistrés (importés ou saisis avant l'application). */
class SyncConvoyeurAccess extends Command
{
    protected $signature = 'mobile:sync-access {--district= : Limiter à un district (nom)} {--dry-run : Compter sans rien créer}';

    protected $description = 'Crée ou met à jour l\'accès mobile de chaque chef de mission / passager (convoyeur d\'office)';

    public function handle(ConvoyeurAccess $access): int
    {
        $query = Personnel::query()->whereIn('fonction', ConvoyeurAccess::FONCTIONS)->where('statut', '!=', 'inactif')
            ->where(fn ($q) => $q->whereDoesntHave('user')->orWhereNull('identifiant'))
            ->when($this->option('district'), fn ($q, $name) => $q->whereHas('district', fn ($d) => $d->where('name', 'like', "%{$name}%")));

        $this->info($query->count().' personne(s) sans accès mobile.');
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $created = 0;
        $bar = $this->output->createProgressBar($query->count());
        $query->orderBy('district_id')->chunkById(100, function ($chunk) use ($access, &$created, $bar) {
            foreach ($chunk as $personnel) {
                $access->sync($personnel);
                $created++;
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info("{$created} accès créé(s). Les codes d'accès provisoires sont visibles dans la liste du personnel.");

        return self::SUCCESS;
    }
}
