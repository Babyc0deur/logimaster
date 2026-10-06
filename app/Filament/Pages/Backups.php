<?php

namespace App\Filament\Pages;

use App\Domain\Backup\BackupManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Sauvegardes (administrateur national) : état, lancement et vérification manuels, téléchargement d'une archive. */
class Backups extends Page
{
    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Sauvegardes';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $title = 'Sauvegardes';

    protected static ?string $slug = 'sauvegardes';

    protected static bool $shouldRegisterNavigation = false;   // accessible depuis le menu utilisateur

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isNational();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')->label('Sauvegarder maintenant')->icon('heroicon-o-arrow-up-tray')
                ->requiresConfirmation()->modalDescription('Copie de la base et des photos vers le stockage des sauvegardes.')
                ->action(function (BackupManager $backups) {
                    try {
                        $r = $backups->create();
                        Notification::make()->title('Sauvegarde terminée')->body(sprintf('%s · %.1f Mo · %d photo(s)', basename($r['path']), $r['size'] / 1048576, $r['photos']))->success()->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Sauvegarde échouée')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('verify')->label('Vérifier la dernière')->icon('heroicon-o-shield-check')->color('gray')
                ->action(function (BackupManager $backups) {
                    try {
                        $r = $backups->verify();
                        Notification::make()->title('Sauvegarde valide')->body(basename($r['path']).' : base intacte, '.($r['counts']['vehicles'] ?? 0).' véhicules, '.($r['counts']['livraisons_espc'] ?? 0).' livraisons, '.$r['photos'].' photo(s).')->success()->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Sauvegarde invalide')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /** Téléchargement d'une archive (contient des données personnelles : réservé au national). */
    public function download(string $path): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $backups = app(BackupManager::class);
        abort_unless(collect($backups->list())->contains('path', $path), 404);

        return $backups->disk()->download($path, basename($path));
    }

    protected function getViewData(): array
    {
        $backups = app(BackupManager::class);
        try {
            $list = $backups->list();
            $error = null;
        } catch (Throwable $e) {
            $list = [];
            $error = $e->getMessage();
        }

        return ['list' => $list, 'external' => $backups->external(), 'error' => $error, 'due' => $error === null && ($list === [] || $list[0]['at']->lt(now()->subHours(26)))];
    }
}
