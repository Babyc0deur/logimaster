<?php

namespace App\Domain\Chronogramme;

use App\Models\Chronogramme;
use App\Models\User;
use App\Notifications\PlanningValidated;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Validation du chronogramme par un superviseur : le district soumet le planning d'un mois, le superviseur
 * (permission validate_chronogrammes) le valide ou le refuse (motif). Un planning validé est verrouillé :
 * date, véhicule, chauffeur, circuit et horaire ne bougent plus ; seules l'exécution (réalisée, livraisons) reste modifiable.
 */
class ChronogrammeWorkflow
{
    /** Planifications du mois d'un district (hors annulées), éventuellement limitées à une sélection. */
    private function plans(string $districtId, CarbonImmutable $month, ?array $ids = null): Collection
    {
        return Chronogramme::where('district_id', $districtId)->where('statut', '!=', 'annulee')
            ->whereDateBetween('date_prevue', $month->startOfMonth(), $month->endOfMonth())
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))->get();
    }

    /** Soumet à validation les plans en brouillon ou refusés. @return int nombre de plans soumis */
    public function submit(string $districtId, CarbonImmutable $month, User $by, ?array $ids = null): int
    {
        $plans = $this->plans($districtId, $month, $ids)->whereIn('validation_statut', ['brouillon', 'refuse']);
        foreach ($plans as $plan) {
            $plan->update(['validation_statut' => 'soumis', 'soumis_at' => now(), 'motif_refus' => null]);
        }
        if ($plans->isNotEmpty()) {
            $this->notify('validate_chronogrammes', $districtId, $by, 'Chronogramme à valider',
                "{$plans->count()} sortie(s) planifiée(s) en ".$month->translatedFormat('F Y').' soumise(s) par '.$by->name.'.', 'warning');
        }

        return $plans->count();
    }

    /** Valide les plans soumis (verrouillage). @return int */
    public function validate(string $districtId, CarbonImmutable $month, User $by, ?array $ids = null): int
    {
        $plans = $this->plans($districtId, $month, $ids)->where('validation_statut', 'soumis');
        foreach ($plans as $plan) {
            $plan->update(['validation_statut' => 'valide', 'valide_par' => $by->getKey(), 'valide_at' => now(), 'motif_refus' => null]);
        }
        $plans->isNotEmpty() && $this->notify('update_chronogrammes', $districtId, $by, 'Chronogramme validé',
            "{$plans->count()} sortie(s) de ".$month->translatedFormat('F Y').' validée(s) par '.$by->name.'.', 'success');
        $plans->isNotEmpty() && $this->notifyCrews($plans);

        return $plans->count();
    }

    /** Refuse les plans soumis avec un motif ; ils repassent modifiables. @return int */
    public function refuse(string $districtId, CarbonImmutable $month, User $by, string $motif, ?array $ids = null): int
    {
        abort_if(trim($motif) === '', 422, 'Le motif du refus est obligatoire.');
        $plans = $this->plans($districtId, $month, $ids)->where('validation_statut', 'soumis');
        foreach ($plans as $plan) {
            $plan->update(['validation_statut' => 'refuse', 'valide_par' => $by->getKey(), 'valide_at' => now(), 'motif_refus' => $motif]);
        }
        $plans->isNotEmpty() && $this->notify('update_chronogrammes', $districtId, $by, 'Chronogramme refusé',
            "{$plans->count()} sortie(s) de ".$month->translatedFormat('F Y')." refusée(s) : {$motif}", 'danger');

        return $plans->count();
    }

    /** Lève la validation (retour en brouillon) pour permettre une modification exceptionnelle. @return int */
    public function reopen(string $districtId, CarbonImmutable $month, User $by, ?array $ids = null): int
    {
        $plans = $this->plans($districtId, $month, $ids)->where('validation_statut', 'valide');
        foreach ($plans as $plan) {
            $plan->update(['validation_statut' => 'brouillon', 'valide_par' => null, 'valide_at' => null, 'soumis_at' => null]);
        }
        $plans->isNotEmpty() && $this->notify('update_chronogrammes', $districtId, $by, 'Validation du chronogramme levée',
            "La validation de {$plans->count()} sortie(s) de ".$month->translatedFormat('F Y').' a été levée par '.$by->name.'.', 'warning');

        return $plans->count();
    }

    /** Prévient chaque convoyeur (chauffeur, chef de mission, passager) des sorties validées : dans l'application et sur son téléphone. */
    private function notifyCrews(Collection $plans): void
    {
        $byPersonnel = [];
        foreach ($plans->load('personnels:id', 'district:id,name', 'driver.personnel:id,driver_id') as $plan) {
            foreach ($plan->personnels->push($plan->driver?->personnel)->filter()->unique('id') as $personnel) {
                $byPersonnel[$personnel->getKey()]['dates'][] = $plan->date_prevue->toDateString();
                $byPersonnel[$personnel->getKey()]['district'] = $plan->district?->name ?? '';
            }
        }
        if ($byPersonnel === []) {
            return;
        }
        User::whereIn('personnel_id', array_keys($byPersonnel))->where('is_active', true)->get()->each(
            fn (User $user) => $user->notify(new PlanningValidated($byPersonnel[$user->personnel_id]['dates'], $byPersonnel[$user->personnel_id]['district']))
        );
    }

    /** Notifie (in-app) les utilisateurs actifs ayant la permission et l'accès au district, sauf l'auteur de l'action. */
    private function notify(string $permission, string $districtId, User $by, string $title, string $body, string $color): void
    {
        User::permission($permission)->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->getKey() !== $by->getKey() && $u->canAccessDistrict($districtId))
            ->each(fn (User $u) => Notification::make()->title($title)->body($body)->color($color)->sendToDatabase($u));
    }
}
