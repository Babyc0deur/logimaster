<?php

namespace App\Domain\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegardes de LogiMaster : une archive zip contenant
 *   - database.sqlite : copie cohérente de la base, prise pendant que l'application tourne (VACUUM INTO) ;
 *   - photos/… : factures, photos de compteur, bons de livraison ;
 *   - manifest.json : date, nombre de lignes par table, empreinte de la base.
 * Les archives partent sur le disque « backups » (stockage S3 externe) ; rotation : quotidiennes sur N jours,
 * puis la première de chaque mois sur M mois. Une archive est toujours vérifiée (intégrité + contenu) avant l'envoi
 * et peut être vérifiée à nouveau après coup en la restaurant dans une base temporaire.
 */
class BackupManager
{
    /** Tables dont le nombre de lignes est noté dans le manifeste et comparé lors des vérifications. */
    public const KEY_TABLES = ['districts', 'vehicles', 'espc', 'circuits', 'personnels', 'chronogrammes', 'sorties_vehicules', 'livraisons_espc', 'ravitaillements', 'users'];

    private const PATTERN = '/logimaster-(\d{8})-(\d{6})\.zip$/';

    public function external(): bool
    {
        return (bool) config('logimaster.backup.external');
    }

    public function disk(): Filesystem
    {
        return $this->external() ? Storage::disk('backups') : Storage::disk('local');
    }

    private function folder(): string
    {
        return $this->external() ? trim((string) config('logimaster.backup.folder'), '/') : 'backups';
    }

    // ------------------------------------------------------------------ création

    /** @return array{path: string, size: int, counts: array<string, int>, photos: int} */
    public function create(): array
    {
        $now = CarbonImmutable::now('UTC');
        $work = $this->workDir();
        $copy = $work.'/database.sqlite';
        try {
            $this->snapshotDatabase($copy);
            $counts = $this->check($copy);

            $zipPath = $work.'/archive.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer l\'archive de sauvegarde.');
            }
            $zip->addFile($copy, 'database.sqlite');
            $photos = 0;
            foreach (config('logimaster.backup.photos') as $dir) {
                foreach (Storage::disk('local')->allFiles($dir) as $file) {
                    $zip->addFile(Storage::disk('local')->path($file), 'photos/'.$file);
                    $photos++;
                }
            }
            $zip->addFromString('manifest.json', json_encode([
                'application' => 'LogiMaster', 'created_at' => $now->toIso8601String(), 'database_sha256' => hash_file('sha256', $copy),
                'counts' => $counts, 'photos' => $photos, 'app_env' => config('app.env'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->close();

            $path = $this->folder().'/'.$now->format('Y').'/logimaster-'.$now->format('Ymd-His').'.zip';
            $stream = fopen($zipPath, 'rb');
            $this->disk()->writeStream($path, $stream);
            is_resource($stream) && fclose($stream);
            $size = filesize($zipPath);
        } finally {
            File::deleteDirectory($work);
        }
        $this->prune($now);

        return ['path' => $path, 'size' => $size, 'counts' => $counts, 'photos' => $photos];
    }

    /** Copie cohérente de la base SQLite, même pendant des écritures (VACUUM INTO). */
    private function snapshotDatabase(string $target): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('La sauvegarde intégrée ne gère que SQLite (base actuelle : '.DB::getDriverName().').');
        }
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($target));
    }

    /**
     * Contrôle d'une copie de base : intégrité SQLite + lecture des tables clés.
     *
     * @return array<string, int>
     */
    public function check(string $sqliteFile): array
    {
        $pdo = new PDO('sqlite:'.$sqliteFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrity !== 'ok') {
            throw new RuntimeException('Base de la sauvegarde corrompue : '.$integrity);
        }
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $counts = [];
        foreach (self::KEY_TABLES as $t) {
            if (in_array($t, $tables, true)) {
                $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
            }
        }
        $pdo = null;

        return $counts;
    }

    // ------------------------------------------------------------------ inventaire, rotation

    /** @return array<int, array{path: string, at: CarbonImmutable, size: int}> du plus récent au plus ancien */
    public function list(): array
    {
        $out = [];
        foreach ($this->disk()->allFiles($this->folder()) as $file) {
            if (preg_match(self::PATTERN, $file, $m)) {
                $out[] = ['path' => $file, 'at' => CarbonImmutable::createFromFormat('Ymd His', $m[1].' '.$m[2], 'UTC'), 'size' => (int) $this->disk()->size($file)];
            }
        }
        usort($out, fn ($a, $b) => $b['at'] <=> $a['at']);

        return $out;
    }

    public function latest(): ?array
    {
        return $this->list()[0] ?? null;
    }

    /** Une sauvegarde est due si la dernière a plus de $hours heures (ou s'il n'y en a aucune). */
    public function isDue(int $hours = 20): bool
    {
        $last = $this->latest();

        return ! $last || $last['at']->lt(CarbonImmutable::now('UTC')->subHours($hours));
    }

    /** Garde les sauvegardes des N derniers jours, puis la première de chaque mois sur M mois ; supprime le reste. @return int supprimées */
    public function prune(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now('UTC');
        $dailyLimit = $now->subDays((int) config('logimaster.backup.keep_daily'));
        $monthlyLimit = $now->subMonths((int) config('logimaster.backup.keep_monthly'))->startOfMonth();
        $keepMonthly = [];
        foreach (array_reverse($this->list()) as $b) {   // du plus ancien au plus récent : la première de chaque mois
            $keepMonthly[$b['at']->format('Y-m')] ??= $b['path'];
        }
        $deleted = 0;
        foreach ($this->list() as $i => $b) {
            if ($i === 0 || $b['at']->gte($dailyLimit)) {
                continue;   // la plus récente est toujours gardée
            }
            if ($b['at']->gte($monthlyLimit) && in_array($b['path'], $keepMonthly, true)) {
                continue;
            }
            $this->disk()->delete($b['path']);
            $deleted++;
        }

        return $deleted;
    }

    // ------------------------------------------------------------------ vérification et restauration

    /**
     * Test de restauration : télécharge l'archive, l'ouvre, contrôle la base et compare au manifeste. Ne touche pas à la base en service.
     *
     * @return array{path: string, counts: array<string, int>, photos: int, created_at: string}
     */
    public function verify(?string $path = null): array
    {
        $path ??= $this->latest()['path'] ?? throw new RuntimeException('Aucune sauvegarde à vérifier.');
        $work = $this->workDir();
        try {
            [$db, $manifest, $photos] = $this->extract($path, $work);
            $counts = $this->check($db);
            if (($manifest['database_sha256'] ?? null) !== hash_file('sha256', $db)) {
                throw new RuntimeException('Empreinte de la base différente de celle du manifeste : archive altérée.');
            }
            if (($manifest['counts'] ?? []) != $counts) {
                throw new RuntimeException('Nombre de lignes différent de celui du manifeste.');
            }

            return ['path' => $path, 'counts' => $counts, 'photos' => count($photos), 'created_at' => (string) ($manifest['created_at'] ?? '')];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Remplace la base en service et les photos par celles d'une sauvegarde (vérifiée d'abord). La base actuelle est
     * conservée à côté (database.sqlite.avant-restauration-…) tant qu'elle n'est pas vide.
     *
     * @return array{path: string, counts: array<string, int>, photos: int}
     */
    public function restore(?string $path = null): array
    {
        $path ??= $this->latest()['path'] ?? throw new RuntimeException('Aucune sauvegarde à restaurer.');
        $work = $this->workDir();
        try {
            [$db, , $photos] = $this->extract($path, $work);
            $counts = $this->check($db);

            $target = DB::connection()->getDatabaseName();
            DB::disconnect();
            if (is_file($target) && filesize($target) > 0) {
                copy($target, $target.'.avant-restauration-'.now()->format('Ymd-His'));
            }
            foreach (['-wal', '-shm', '-journal'] as $suffix) {
                @unlink($target.$suffix);
            }
            if (! copy($db, $target)) {
                throw new RuntimeException('Impossible de remplacer la base de données.');
            }
            DB::reconnect();
            foreach ($photos as $relative => $file) {
                Storage::disk('local')->put($relative, file_get_contents($file));
            }
            Artisan::call('migrate', ['--force' => true]);   // une sauvegarde plus ancienne que le code : schéma mis à niveau

            return ['path' => $path, 'counts' => $counts, 'photos' => count($photos)];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: array<string, string>} base extraite, manifeste, photos [chemin relatif => fichier] */
    private function extract(string $path, string $work): array
    {
        $local = $work.'/archive.zip';
        $in = $this->disk()->readStream($path) ?: throw new RuntimeException("Sauvegarde introuvable : {$path}");
        $out = fopen($local, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        is_resource($in) && fclose($in);

        $zip = new ZipArchive;
        if ($zip->open($local) !== true) {
            throw new RuntimeException('Archive de sauvegarde illisible.');
        }
        $db = $work.'/database.sqlite';
        $content = $zip->getFromName('database.sqlite');
        if ($content === false) {
            throw new RuntimeException('Base de données absente de l\'archive.');
        }
        file_put_contents($db, $content);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true) ?: [];
        $photos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // seulement les dossiers de photos attendus, sans remontée de dossier
            if (preg_match('#^photos/((?:'.implode('|', array_map('preg_quote', config('logimaster.backup.photos'))).')/[^/]+\.(?:jpe?g|png|webp|pdf))$#i', $name, $m) && ! str_contains($m[1], '..')) {
                $file = $work.'/p'.$i;
                file_put_contents($file, $zip->getFromIndex($i));
                $photos[$m[1]] = $file;
            }
        }
        $zip->close();

        return [$db, $manifest, $photos];
    }

    private function workDir(): string
    {
        $dir = storage_path('app/backup-work/'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
