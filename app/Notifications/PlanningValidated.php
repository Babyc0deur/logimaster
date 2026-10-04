<?php

namespace App\Notifications;

use App\Domain\Mobile\WebPushSender;
use Illuminate\Notifications\Notification;
use Throwable;

/** « Chronogramme validé » : prévient le convoyeur (dans l'application et par notification sur son téléphone). */
class PlanningValidated extends Notification
{
    /** @param  array<int, string>  $dates  dates (Y-m-d) des sorties validées dont il fait partie */
    public function __construct(public array $dates, public string $districtName)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function title(): string
    {
        return 'Chronogramme validé';
    }

    public function body(): string
    {
        sort($this->dates);
        $n = count($this->dates);
        $first = \Carbon\CarbonImmutable::parse($this->dates[0])->translatedFormat('l j F');

        return $n === 1
            ? "Votre sortie du {$first} est validée ({$this->districtName})."
            : "{$n} sorties validées à partir du {$first} ({$this->districtName}).";
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => '/m', 'dates' => $this->dates];
    }

    /** Envoi push à tous les téléphones du convoyeur ; une erreur d'envoi ne bloque jamais la validation. */
    public function sendWebPush(object $notifiable): void
    {
        $sender = app(WebPushSender::class);
        foreach ($notifiable->pushSubscriptions as $subscription) {
            try {
                $sender->send($subscription, ['title' => $this->title(), 'body' => $this->body(), 'url' => '/m', 'tag' => 'planning']);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
