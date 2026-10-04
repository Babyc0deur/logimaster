<?php

namespace App\Filament\Support;

use App\Domain\Mobile\ConvoyeurAccess;
use App\Models\Personnel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Accès mobile des convoyeurs. Les chefs de mission et passagers sont convoyeurs d'office : leur accès se crée avec leur
 * fiche. Le bureau n'a qu'à lire l'identifiant et le code provisoire pour les remettre, ou à réinitialiser un code perdu.
 */
class MobileAccessActions
{
    /** Identifiant et code d'accès à remettre au convoyeur (le code disparaît dès qu'il choisit son mot de passe). */
    public static function credentials(): Action
    {
        return Action::make('acces_mobile')->label('Accès mobile')->icon('heroicon-o-key')->color('info')
            ->visible(fn (Personnel $record) => ConvoyeurAccess::enabled() && $record->identifiant && auth()->user()?->can('update_personnels'))
            ->modalHeading(fn (Personnel $record) => 'Accès mobile de '.$record->nom_complet)
            ->modalContent(fn (Personnel $record) => view('filament.modals.mobile-credentials', ['personnel' => $record->fresh('user')]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Fermer');
    }

    /** Code perdu ou compte à reprendre : nouveau code provisoire, appareils déconnectés. */
    public static function resetCode(): Action
    {
        return Action::make('reinitialiser_code')->label('Réinitialiser le code')->icon('heroicon-o-arrow-path')->color('warning')
            ->visible(fn (Personnel $record) => ConvoyeurAccess::enabled() && $record->identifiant && auth()->user()?->can('update_personnels'))
            ->requiresConfirmation()
            ->modalDescription('Un nouveau code provisoire est généré. Le convoyeur sera déconnecté de ses appareils et devra choisir un nouveau mot de passe.')
            ->action(function (Personnel $record) {
                $code = app(ConvoyeurAccess::class)->resetCode($record);
                Notification::make()->success()->persistent()->title('Nouveau code pour '.$record->nom_complet)
                    ->body("Identifiant : {$record->identifiant}\nCode d'accès provisoire : {$code}")->send();
            });
    }

    /** Fenêtre « QR code d'installation » : à scanner avec l'appareil photo du téléphone. */
    public static function qr(): Action
    {
        return Action::make('qr_installation')->label('QR code d\'installation')->icon('heroicon-o-qr-code')->color('gray')
            ->modalHeading('Installer l\'application mobile')
            ->modalContent(fn () => view('filament.modals.mobile-qr', [
                'url' => \App\Http\Controllers\MobileAppController::appUrl(),
                'svg' => \App\Http\Controllers\MobileAppController::qrSvg(null, 260),
            ]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Fermer');
    }
}
