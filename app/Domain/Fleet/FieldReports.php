<?php

namespace App\Domain\Fleet;

use App\Models\Immobilisation;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vidange;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Signalements terrain : le convoyeur déclare depuis son téléphone une vidange faite ou une panne ; le bureau les valide
 * (la vidange ou l'immobilisation est alors enregistrée, avec ses effets sur le véhicule) ou les rejette.
 */
class FieldReports
{
    /** Intervalle de vidange par défaut quand le prochain kilométrage n'est pas indiqué. */
    public static function defaultInterval(): int
    {
        return (int) config('logimaster.vidange_interval_km', 5000);
    }

    /** Prévient le bureau du district (cloche de l'administration). */
    public function notifyOffice(Signalement $s): void
    {
        $users = User::where('is_active', true)->whereNull('personnel_id')->get()
            ->filter(fn (User $u) => $u->can('update_vehicles') && $u->canAccessDistrict($s->district_id));
        if ($users->isEmpty()) {
            return;
        }
        $s->loadMissing('vehicle:id,immatriculation', 'personnel:id,nom_complet');
        $n = Notification::make()
            ->title(($s->type === 'vidange' ? 'Vidange déclarée' : 'Panne signalée').' — '.$s->vehicle?->immatriculation)
            ->body(trim(($s->personnel?->nom_complet ? 'Par '.$s->personnel->nom_complet.'. ' : '').($s->description ?? '')).' À valider dans « Signalements terrain ».')
            ->icon($s->type === 'vidange' ? 'heroicon-o-wrench-screwdriver' : 'heroicon-o-exclamation-triangle');
        ($s->type === 'vidange' ? $n->info() : $n->warning())->sendToDatabase($users);
    }

    /**
     * Validation par le bureau : crée la vidange ou l'immobilisation (valeurs éventuellement corrigées dans $data).
     *
     * @param  array<string, mixed>  $data
     */
    public function validate(Signalement $s, User $by, array $data = []): Vidange|Immobilisation
    {
        if ($s->statut !== 'nouveau') {
            throw new RuntimeException('Ce signalement a déjà été traité.');
        }

        return DB::transaction(function () use ($s, $by, $data) {
            $date = ($data['date'] ?? null) ?: ($s->signale_at ?? $s->created_at)->toDateString();
            if ($s->type === 'vidange') {
                $km = (int) ($data['km'] ?? $s->km ?? $s->vehicle->km_actuel);
                $cible = Vidange::create([
                    'vehicle_id' => $s->vehicle_id, 'district_id' => $s->district_id, 'date' => $date, 'km' => $km,
                    'type' => $data['type_vidange'] ?? $s->detail('type_vidange', 'simple'),
                    'montant' => $data['montant'] ?? $s->detail('montant'), 'prestataire' => $data['prestataire'] ?? $s->detail('prestataire'),
                    'prochain_km' => $data['prochain_km'] ?? ($km + self::defaultInterval()),
                    'observations' => trim('Déclarée depuis l\'application convoyeur. '.($s->description ?? '')),
                    'facture_path' => $s->photo,
                ]);
            } else {
                $cible = Immobilisation::create([
                    'district_id' => $s->district_id, 'vehicle_id' => $s->vehicle_id, 'date_debut' => $date,
                    'motif' => $data['motif'] ?? $s->detail('motif', 'panne'), 'description' => $data['description'] ?? $s->description,
                    'statut' => 'en_cours',
                ]);
                $s->vehicle->update(['statut' => 'en_maintenance']);
            }
            $s->update(['statut' => 'valide', 'traite_par' => $by->getKey(), 'traite_at' => now(), 'cible_type' => $cible->getMorphClass(), 'cible_id' => $cible->getKey(),
                'commentaire_bureau' => $data['commentaire'] ?? null]);

            return $cible;
        });
    }

    public function reject(Signalement $s, User $by, ?string $comment): void
    {
        if ($s->statut !== 'nouveau') {
            throw new RuntimeException('Ce signalement a déjà été traité.');
        }
        $s->update(['statut' => 'rejete', 'traite_par' => $by->getKey(), 'traite_at' => now(), 'commentaire_bureau' => $comment]);
    }
}
