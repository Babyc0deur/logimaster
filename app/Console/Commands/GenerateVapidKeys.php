<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid {--write : Ajoute les clés au fichier .env (sans écraser des clés existantes)}';

    protected $description = 'Génère la paire de clés VAPID des notifications sur téléphone';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        if ($this->option('write')) {
            $env = base_path('.env');
            $content = is_file($env) ? file_get_contents($env) : '';
            if (str_contains($content, 'VAPID_PUBLIC_KEY=')) {
                $this->warn('Des clés VAPID existent déjà dans .env : rien n\'a été modifié.');

                return self::SUCCESS;
            }
            file_put_contents($env, rtrim($content)."\n\nVAPID_SUBJECT=mailto:admin@logimaster.test\nVAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}\n");
            $this->info('Clés VAPID ajoutées à .env.');

            return self::SUCCESS;
        }

        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");

        return self::SUCCESS;
    }
}
