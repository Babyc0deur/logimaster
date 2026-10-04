<?php

namespace App\Domain\Mobile;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envoie une notification Web Push à un téléphone. Isolé derrière cette classe pour pouvoir être remplacé dans les tests
 * (aucun appel réseau) et pour supprimer les abonnements expirés.
 */
class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    /**
     * @param  array<string, mixed>  $payload  title, body, url, tag
     * @return bool true si le service push a accepté la notification
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        if (! $this->configured()) {
            return false;
        }
        if (config('webpush.openssl_conf') && ! getenv('OPENSSL_CONF')) {
            putenv('OPENSSL_CONF='.config('webpush.openssl_conf'));
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 86400]);

        $report = $webPush->sendOneNotification(
            Subscription::create(['endpoint' => $subscription->endpoint, 'keys' => ['p256dh' => $subscription->p256dh, 'auth' => $subscription->auth]]),
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        if ($report->isSubscriptionExpired()) {
            $subscription->delete();   // téléphone désinstallé ou autorisation retirée

            return false;
        }
        $subscription->forceFill(['last_used_at' => now()])->save();

        return $report->isSuccess();
    }
}
