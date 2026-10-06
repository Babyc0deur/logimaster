<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupManager;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** backup:restore — remplace la base et les photos par une sauvegarde. */
class BackupRestore extends Command
{
    protected $signature = 'backup:restore {path? : Fichier de sauvegarde (défaut : le plus récent)}
        {--if-empty : Seulement si la base ne contient aucun véhicule (serveur reparti de zéro)}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Restaure la base et les photos depuis une sauvegarde';

    public function handle(BackupManager $backups): int
    {
        if ($this->option('if-empty')) {
            if (! config('logimaster.backup.restore_if_empty')) {
                return self::SUCCESS;
            }
            if (Schema::hasTable('vehicles') && DB::table('vehicles')->exists()) {
                $this->info('La base contient des données : pas de restauration.');

                return self::SUCCESS;
            }
            try {
                if (! $backups->latest()) {
                    $this->info('Aucune sauvegarde disponible : pas de restauration.');

                    return self::SUCCESS;
                }
            } catch (Throwable $e) {
                $this->warn('Stockage des sauvegardes inaccessible : '.$e->getMessage());

                return self::SUCCESS;   // ne bloque jamais le démarrage
            }
        } elseif (! $this->option('force') && ! $this->confirm('Remplacer la base et les photos actuelles par la sauvegarde ?')) {
            return self::FAILURE;
        }

        try {
            $r = $backups->restore($this->argument('path'));
            $this->info("Restauré depuis {$r['path']} : ".collect($r['counts'])->map(fn ($n, $t) => "{$t} {$n}")->join(', ').", {$r['photos']} photo(s).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            BackupRun::alertAdmins('Restauration échouée', $e->getMessage());
            $this->error('Restauration échouée : '.$e->getMessage());

            return $this->option('if-empty') ? self::SUCCESS : self::FAILURE;
        }
    }
}
