<?php

namespace App\Providers;

use App\Domain\Mobile\WebPushSender;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\ServiceProvider;

/** Déclare le canal de notification « webpush » (notifications du téléphone). */
class WebPushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebPushSender::class);
    }

    public function boot(): void
    {
        $this->app->make(ChannelManager::class)->extend('webpush', fn () => new class
        {
            public function send(object $notifiable, Notification $notification): void
            {
                method_exists($notification, 'sendWebPush') && $notification->sendWebPush($notifiable);
            }
        });
    }
}
