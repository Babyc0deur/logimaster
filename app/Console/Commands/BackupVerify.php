<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupManager;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** backup:verify — test de restauration dans une base temporaire (la base en service n'est pas touchée). */
class BackupVerify extends Command
{
    protected $signature = 'backup:verify {path? : Fichier de sauvegarde (défaut : le plus récent)}';

    protected $description = 'Vérifie une sauvegarde en la restaurant dans une base temporaire';

    public function handle(BackupManager $backups): int
    {
        try {
            $r = $backups->verify($this->argument('path'));
            $this->info("Sauvegarde valide : {$r['path']} ({$r['photos']} photo(s)).");
            $this->table(['Table', 'Lignes'], collect($r['counts'])->map(fn ($n, $t) => [$t, $n])->values()->all());

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            BackupRun::alertAdmins('Sauvegarde invalide', $e->getMessage());
            $this->error('Sauvegarde invalide : '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
