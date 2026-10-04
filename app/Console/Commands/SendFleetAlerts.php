<?php

namespace App\Console\Commands;

use App\Domain\Fleet\MaintenancePlanner;
use App\Models\District;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Notifications in-app des alertes de maintenance aux utilisateurs du district.
 * Échéances datées (CT, assurance, documents) : à J-30, J-15, J-7, le jour J et à l'expiration.
 * Alertes kilométriques / immobilisations : une fois par semaine tant qu'elles persistent.
 */
class SendFleetAlerts extends Command
{
    protected $signature = 'fleet:alerts {--dry-run : Affiche sans notifier}';

    protected $description = 'Notifie les alertes de maintenance (vidanges, CT, assurances, documents, immobilisations)';

    public function handle(MaintenancePlanner $planner): int
    {
        $sent = 0;

        // Destinataires : tout utilisateur actif ayant accès au district (rattachement direct ou région du responsable régional).
        $users = \App\Models\User::permission('view_dashboard')->where('is_active', true)->get();

        foreach (District::all() as $district) {
            $recipients = $users->filter(fn ($u) => $u->canAccessDistrict($district->id));
            if ($recipients->isEmpty()) {
                continue;
            }

            foreach (\App\Domain\Fleet\AlertCenter::all([$district->id]) as $alert) {
                if (! $this->due($alert, $district->id)) {
                    continue;
                }
                $sent++;
                if ($this->option('dry-run')) {
                    $this->line("[{$district->name}] {$alert['level']->emoji()} {$alert['message']}");

                    continue;
                }
                foreach ($recipients as $user) {
                    Notification::make()
                        ->title("{$alert['level']->emoji()} {$alert['level']->label()} — {$district->name}")
                        ->body($alert['message'])
                        ->color($alert['level']->color())
                        ->sendToDatabase($user);
                }
            }
        }

        $this->info("{$sent} alerte(s) ".($this->option('dry-run') ? 'à notifier.' : 'notifiée(s).'));

        return self::SUCCESS;
    }

    private function due(array $alert, string $districtId): bool
    {
        if (isset($alert['days'])) {
            return $alert['days'] < 0
                ? Cache::add("fleet-alert:{$alert['type']}:".($alert['vehicle_id'] ?? $alert['driver_id'] ?? $alert['document_id'] ?? $districtId).':expired', 1, now()->addDays(7))
                : in_array($alert['days'], [30, 15, 7, 0], true);
        }

        $key = "fleet-alert:{$alert['type']}:".($alert['vehicle_id'] ?? $alert['budget_id'] ?? $districtId).":{$alert['level']->value}";

        return Cache::add($key, 1, now()->addDays(7));
    }
}
