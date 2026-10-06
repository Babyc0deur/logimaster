<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupManager;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** backup:run — sauvegarde (base + photos) vers le stockage externe, avec rotation. */
class BackupRun extends Command
{
    protected $signature = 'backup:run {--if-due : Seulement si la dernière sauvegarde a plus de 20 h (serveur mis en veille, redémarrages)}';

    protected $description = 'Sauvegarde la base et les photos vers le stockage externe (rotation 30 jours + 12 mois)';

    public function handle(BackupManager $backups): int
    {
        try {
            if ($this->option('if-due') && ! $backups->isDue()) {
                $this->info('Dernière sauvegarde récente : rien à faire.');

                return self::SUCCESS;
            }
            if (! $backups->external()) {
                $this->warn('Aucun stockage externe configuré (BACKUP_S3_*) : sauvegarde écrite sur ce serveur, perdue avec lui.');
            }
            $r = $backups->create();
            $this->info(sprintf('Sauvegarde %s : %.1f Mo, %d photo(s), %s.', $r['path'], $r['size'] / 1048576, $r['photos'],
                collect($r['counts'])->only(['vehicles', 'sorties_vehicules', 'livraisons_espc'])->map(fn ($n, $t) => "{$t} {$n}")->join(', ')));

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            self::alertAdmins('Sauvegarde échouée', $e->getMessage());
            $this->error('Sauvegarde échouée : '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /** Prévient les administrateurs nationaux dans l'application (cloche) ; l'erreur part aussi dans Sentry via report(). */
    public static function alertAdmins(string $title, string $body): void
    {
        try {
            $admins = User::role(User::ROLE_PRES_ADMIN)->where('is_active', true)->get();
            $admins->isNotEmpty() && Notification::make()->title($title)->body($body)->danger()->sendToDatabase($admins);
        } catch (Throwable) {
            // la notification ne doit jamais masquer l'erreur d'origine
        }
    }
}
