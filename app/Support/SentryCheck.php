<?php

namespace App\Support;

use RuntimeException;
use Sentry\SentrySdk;

/**
 * Vérification du suivi des erreurs depuis l'administration (utile quand le serveur n'offre pas de terminal) :
 * une erreur de test passe par le même chemin que les vraies erreurs (report()), puis est envoyée immédiatement.
 */
class SentryCheck
{
    public static function configured(): bool
    {
        return filled(config('sentry.dsn'));
    }

    /** @return array{ok: bool, message: string} */
    public static function send(): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'message' => "Sentry n'est pas configuré : ajoutez la variable SENTRY_LARAVEL_DSN sur le serveur, puis redéployez."];
        }

        report(new RuntimeException('Test Sentry LogiMaster — erreur volontaire envoyée depuis l\'administration le '.now()->format('d/m/Y H:i:s')));

        $hub = SentrySdk::getCurrentHub();
        $id = $hub->getLastEventId();
        $hub->getClient()?->flush(5);

        return $id
            ? ['ok' => true, 'message' => "Erreur de test envoyée (identifiant {$id}). Elle doit apparaître dans Sentry, onglet Issues, d'ici une minute."]
            : ['ok' => false, 'message' => "L'erreur n'a pas été transmise à Sentry : vérifiez la valeur de SENTRY_LARAVEL_DSN."];
    }
}
