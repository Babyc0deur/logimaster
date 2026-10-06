<?php

namespace App\Console\Commands;

use App\Domain\Backup\BackupManager;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** backup:list — sauvegardes disponibles. */
class BackupList extends Command
{
    protected $signature = 'backup:list';

    protected $description = 'Liste les sauvegardes disponibles';

    public function handle(BackupManager $backups): int
    {
        $rows = array_map(fn ($b) => [$b['at']->setTimezone(config('app.timezone'))->format('d/m/Y H:i'), $b['path'], number_format($b['size'] / 1048576, 1, ',', ' ').' Mo'], $backups->list());
        $this->line($backups->external() ? 'Stockage externe' : 'Stockage local (pas de stockage externe configuré)');
        $rows ? $this->table(['Date', 'Fichier', 'Taille'], $rows) : $this->warn('Aucune sauvegarde.');

        return self::SUCCESS;
    }
}
