<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Journal d'audit (OWASP A09) : complète AuditApiWrites, qui ne trace que l'API.
 * - créations, modifications et suppressions faites depuis l'administration (session web) ;
 * - échecs de connexion, web, API et application mobile.
 */
class AdminAudit
{
    /** Modèles techniques ou recalculés automatiquement : pas de trace. */
    private const IGNORED = [
        AuditLog::class,
        \App\Models\IndicatorSnapshot::class,
        \App\Models\PushSubscription::class,
        \Illuminate\Notifications\DatabaseNotification::class,
    ];

    /** Champs dont la valeur n'est jamais écrite dans le journal. */
    private const SECRET = ['password', 'remember_token', 'code_acces', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    private const ACTIONS = ['created' => 'création', 'updated' => 'modification', 'deleted' => 'suppression'];

    public static function register(): void
    {
        foreach (array_keys(self::ACTIONS) as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($event) {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    self::modelChanged($model, $event);
                }
            });
        }
        Event::listen(Failed::class, fn (Failed $e) => self::failedLogin(
            (string) ($e->credentials['email'] ?? ''),
            $e->guard,
            $e->user?->getAuthIdentifier(),
        ));
    }

    public static function failedLogin(string $login, string $channel, mixed $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => 'connexion refusée',
            'module' => 'auth',
            'metadata' => ['identifiant' => mb_substr($login, 0, 160), 'canal' => $channel, 'ip' => request()?->ip()],
        ]);
    }

    private static function modelChanged(Model $model, string $event): void
    {
        $user = auth('web')->user();
        if (! $user || request()?->is('api/*') || in_array($model::class, self::IGNORED, true)) {   // commandes artisan : aucun utilisateur connecté
            return;
        }
        $fields = $event === 'updated' ? array_keys($model->getChanges()) : [];
        $fields = array_values(array_diff($fields, ['updated_at']));
        if ($event === 'updated' && $fields === []) {
            return;
        }

        AuditLog::create([
            'user_id' => $user->getAuthIdentifier(),
            'action' => self::ACTIONS[$event].' '.class_basename($model),
            'module' => $model->getTable(),
            'metadata' => [
                'id' => $model->getKey(),
                'champs' => array_map(fn ($f) => in_array($f, self::SECRET, true) ? $f.' (masqué)' : $f, $fields),
                'ip' => request()?->ip(),
            ],
        ]);
    }
}
