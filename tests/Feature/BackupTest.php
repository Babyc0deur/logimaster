<?php

namespace Tests\Feature;

use App\Domain\Backup\BackupManager;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Sauvegardes : archive (base + photos + manifeste) envoyée au stockage externe, vérification par restauration à blanc,
 * rotation, restauration réelle. La base de test est un vrai fichier SQLite (la restauration remplace un fichier).
 */
class BackupTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbFile = storage_path('framework/testing/backup-test-'.bin2hex(random_bytes(4)).'.sqlite');
        File::ensureDirectoryExists(dirname($this->dbFile));
        touch($this->dbFile);
        config(['database.connections.sqlite.database' => $this->dbFile, 'logimaster.backup.external' => true, 'logimaster.backup.folder' => 'logimaster']);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
        Storage::fake('backups');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        foreach (glob($this->dbFile.'*') as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function vehicle(string $immat): Vehicle
    {
        $region = Region::firstOrCreate(['name' => 'R'], ['pres_id' => Pres::firstOrCreate(['name' => 'PRES'])->id]);
        $district = District::firstOrCreate(['name' => 'D'], ['region_id' => $region->id, 'sync_id' => 'D', 'sync_password_hash' => 'x']);

        return Vehicle::create(['district_id' => $district->id, 'immatriculation' => $immat]);
    }

    public function test_backup_contains_database_photos_and_manifest_and_verifies(): void
    {
        $this->vehicle('D55031');
        Storage::disk('local')->put('preuves/bon.jpg', 'JPEGDATA');
        Storage::disk('local')->put('autre/secret.txt', 'ne pas sauvegarder');

        $r = app(BackupManager::class)->create();

        $this->assertMatchesRegularExpression('#^logimaster/\d{4}/logimaster-\d{8}-\d{6}\.zip$#', $r['path']);
        Storage::disk('backups')->assertExists($r['path']);
        $this->assertSame(1, $r['counts']['vehicles']);
        $this->assertSame(1, $r['photos']);

        $zipFile = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($zipFile, Storage::disk('backups')->get($r['path']));
        $zip = new ZipArchive;
        $zip->open($zipFile);
        $this->assertNotFalse($zip->getFromName('database.sqlite'));
        $this->assertSame('JPEGDATA', $zip->getFromName('photos/preuves/bon.jpg'));
        $this->assertFalse($zip->getFromName('photos/autre/secret.txt'));
        $this->assertSame(1, json_decode($zip->getFromName('manifest.json'), true)['counts']['vehicles']);
        $zip->close();
        @unlink($zipFile);

        $v = app(BackupManager::class)->verify();
        $this->assertSame(1, $v['counts']['vehicles']);
        $this->assertSame(1, $v['photos']);
    }

    public function test_a_tampered_archive_is_rejected(): void
    {
        $this->vehicle('D1');
        $path = app(BackupManager::class)->create()['path'];

        $zipFile = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($zipFile, Storage::disk('backups')->get($path));
        $zip = new ZipArchive;
        $zip->open($zipFile);
        $zip->addFromString('manifest.json', json_encode(['database_sha256' => 'faux', 'counts' => []]));
        $zip->close();
        Storage::disk('backups')->put($path, file_get_contents($zipFile));
        @unlink($zipFile);

        $this->expectException(RuntimeException::class);
        app(BackupManager::class)->verify($path);
    }

    public function test_rotation_keeps_30_days_then_first_of_each_month_for_12_months(): void
    {
        $now = CarbonImmutable::parse('2026-10-06 02:00', 'UTC');
        $disk = Storage::disk('backups');
        $name = fn (CarbonImmutable $d) => 'logimaster/'.$d->format('Y').'/logimaster-'.$d->format('Ymd-His').'.zip';
        $files = [];
        for ($d = $now->subMonths(15); $d->lte($now); $d = $d->addDays(3)) {
            $disk->put($files[] = $name($d), 'x');
        }
        $disk->put($files[] = $name($now), 'x');

        app(BackupManager::class)->prune($now);
        $left = collect($disk->allFiles('logimaster'))->sort()->values();

        $this->assertTrue($left->contains($name($now)), 'la plus récente est gardée');
        $this->assertTrue($left->every(fn ($f) => ! str_contains($f, '-2025-06') && ! str_contains($f, 'logimaster-202506')), 'au-delà de 12 mois : supprimées');
        $recent = collect($files)->filter(fn ($f) => $f >= $name($now->subDays(30)));
        $this->assertTrue($recent->every(fn ($f) => $left->contains($f)), '30 derniers jours : toutes gardées');
        $older = $left->filter(fn ($f) => $f < $name($now->subDays(30)));
        $this->assertSame($older->count(), $older->map(fn ($f) => substr(basename($f), 11, 6))->unique()->count(), 'au-delà de 30 jours : une par mois');
        $this->assertLessThanOrEqual(13, $older->count());
    }

    public function test_backups_page_is_for_the_national_admin_only(): void
    {
        $this->seed(\Database\Seeders\LogimasterRoleSeeder::class);
        $this->vehicle('D9');
        $district = District::first();
        app(BackupManager::class)->create();

        $admin = \App\Models\User::factory()->create(['is_active' => true]);
        $admin->assignRole(\App\Models\User::ROLE_PRES_ADMIN);
        $this->actingAs($admin, 'web');
        \Filament\Facades\Filament::setTenant($district);
        $this->get("/admin/{$district->id}/sauvegardes")->assertOk()->assertSee('Sauvegardes disponibles')->assertSee('logimaster-', false);

        $manager = \App\Models\User::factory()->create(['is_active' => true]);
        $manager->assignRole(\App\Models\User::ROLE_DISTRICT_MANAGER);
        $manager->districts()->attach($district->id);
        $this->actingAs($manager, 'web');
        $this->get("/admin/{$district->id}/sauvegardes")->assertForbidden();
    }

    public function test_is_due_after_20_hours(): void
    {
        $m = app(BackupManager::class);
        $this->assertTrue($m->isDue());
        $this->vehicle('D2');
        $m->create();
        $this->assertFalse($m->isDue());
        $this->travel(21)->hours();
        $this->assertTrue($m->isDue());
    }

    public function test_restore_brings_back_data_and_photos(): void
    {
        $this->vehicle('AVANT');
        Storage::disk('local')->put('compteurs/c.jpg', 'COMPTEUR');
        app(BackupManager::class)->create();

        Vehicle::query()->forceDelete();
        Storage::disk('local')->delete('compteurs/c.jpg');
        $this->vehicle('APRES');

        $this->artisan('backup:restore', ['--force' => true])->assertSuccessful();

        $this->assertSame(['AVANT'], Vehicle::pluck('immatriculation')->all());
        $this->assertSame('COMPTEUR', Storage::disk('local')->get('compteurs/c.jpg'));
        $this->assertNotEmpty(glob($this->dbFile.'.avant-restauration-*'), 'la base remplacée est conservée à côté');
    }

    public function test_restore_if_empty_never_overwrites_existing_data_and_never_blocks_startup(): void
    {
        $this->vehicle('ICI');
        app(BackupManager::class)->create();
        $this->vehicle('ENCORE');

        $this->artisan('backup:restore', ['--if-empty' => true])->assertSuccessful();
        $this->assertSame(2, Vehicle::count());

        Vehicle::query()->forceDelete();
        $this->artisan('backup:restore', ['--if-empty' => true])->assertSuccessful();
        $this->assertSame(['ICI'], Vehicle::pluck('immatriculation')->all());

        config(['filesystems.disks.backups' => ['driver' => 's3', 'bucket' => 'x', 'key' => 'k', 'secret' => 's', 'region' => 'auto', 'endpoint' => 'http://127.0.0.1:9', 'throw' => true]]);
        Storage::forgetDisk('backups');
        Vehicle::query()->forceDelete();
        $this->artisan('backup:restore', ['--if-empty' => true])->assertSuccessful();   // stockage injoignable : démarrage non bloqué
    }
}
