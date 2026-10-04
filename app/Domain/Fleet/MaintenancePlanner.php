<?php

namespace App\Domain\Fleet;

use App\Models\Document;
use App\Models\Immobilisation;
use App\Models\Setting;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Échéances de maintenance (vidanges, CT, assurance, documents) et alertes associées. */
class MaintenancePlanner
{
    /** Kilométrage moyen quotidien d'un véhicule sur les 90 derniers jours (0 si aucune sortie). */
    public function averageKmPerDay(Vehicle $vehicle): float
    {
        $km = (float) SortieVehicule::where('vehicle_id', $vehicle->id)
            ->whereNotNull('km_arrivee')->where('statut', '!=', 'annulee')
            ->whereDate('date_sortie', '>=', now()->subDays(90)->toDateString())
            ->get(['km_depart', 'km_arrivee'])->sum(fn ($s) => $s->km_arrivee - $s->km_depart);

        return round($km / 90, 2);
    }

    /** Date estimée à laquelle le véhicule atteindra $targetKm, selon son rythme moyen (null si inconnu). */
    public function estimateDateForKm(Vehicle $vehicle, ?int $targetKm, ?int $fromKm = null): ?CarbonImmutable
    {
        $remaining = $targetKm - ($fromKm ?? $vehicle->km_actuel ?? 0);
        $perDay = $this->averageKmPerDay($vehicle);
        if ($targetKm === null || $perDay <= 0) {
            return null;
        }

        return CarbonImmutable::today()->addDays((int) ceil(max(0, $remaining) / $perDay));
    }

    /**
     * Alertes triées par gravité pour les districts donnés (null = tous).
     *
     * @param  array<int, string>|null  $districtIds
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(?array $districtIds): Collection
    {
        $alerts = collect();
        $scope = fn ($q) => $districtIds === null ? $q : $q->whereIn('district_id', $districtIds);

        foreach ($scope(Vehicle::query())->get() as $v) {
            if ($v->km_vidange !== null) {
                $remaining = $v->km_vidange - $v->km_actuel;
                $level = AlertLevel::forKm($remaining);
                if ($level !== AlertLevel::Ok) {
                    $alerts->push($this->alert('vidange', $level, $v, "Vidange : {$remaining} km restants (échéance {$v->km_vidange} km)"));
                }
            }
            foreach (['date_ct' => 'Contrôle technique', 'date_assurance' => 'Assurance'] as $field => $label) {
                if ($v->$field) {
                    $days = (int) today()->diffInDays($v->$field, false);
                    $level = AlertLevel::forDays($days);
                    if ($level !== AlertLevel::Ok) {
                        $text = $days < 0 ? "{$label} expiré(e) depuis ".abs($days).' j' : "{$label} dans {$days} j";
                        $alerts->push($this->alert($field, $days < 0 ? AlertLevel::Urgent : $level, $v, $text) + ['days' => $days]);
                    }
                }
            }
        }

        // Véhicules immobilisés depuis plus de N jours
        $seuil = (int) Setting::get('immobilisation_alerte_jours');
        foreach ($scope(Immobilisation::query())->with('vehicle:id,immatriculation,district_id')->where('statut', '!=', 'terminee')->get() as $i) {
            if ($i->duree_jours > $seuil) {
                $alerts->push($this->alert('immobilisation', AlertLevel::Urgent, $i->vehicle, "Immobilisé depuis {$i->duree_jours} jours ({$i->motif})"));
            }
        }

        // Permis de conduire expirés ou proches de l'expiration (chauffeurs actifs).
        foreach ($scope(\App\Models\Driver::query())->whereNotNull('permis_expiration')->whereIn('statut', ['actif', 'conge', 'absent'])->get() as $d) {
            $days = (int) today()->diffInDays($d->permis_expiration, false);
            $level = $days < 0 ? AlertLevel::Urgent : AlertLevel::forDays($days);
            if ($level !== AlertLevel::Ok) {
                $alerts->push([
                    'type' => 'permis', 'level' => $level, 'district_id' => $d->district_id, 'vehicle_id' => null, 'driver_id' => $d->id, 'days' => $days,
                    'message' => "Permis de {$d->nom_complet} : ".($days < 0 ? 'expiré depuis '.abs($days).' j' : "expire dans {$days} j"),
                ]);
            }
        }

        foreach ($scope(Document::query())->whereNotNull('date_expiration')->get() as $doc) {
            $days = (int) today()->diffInDays($doc->date_expiration, false);
            $level = $days < 0 ? AlertLevel::Urgent : AlertLevel::forDays($days);
            if ($level !== AlertLevel::Ok) {
                $alerts->push([
                    'type' => 'document', 'level' => $level, 'district_id' => $doc->district_id, 'vehicle_id' => null,
                    'document_id' => $doc->id, 'days' => $days, 'message' => "Document {$doc->categorie} : ".($days < 0 ? 'expiré' : "expire dans {$days} j"),
                ]);
            }
        }

        return $alerts->sortBy(fn ($a) => $a['level']->rank())->values();
    }

    /**
     * Événements du calendrier de maintenance entre deux dates.
     *
     * @param  array<int, string>|null  $districtIds
     * @return Collection<int, array{date: string, type: string, label: string, level: AlertLevel}>
     */
    public function events(?array $districtIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $events = collect();
        $vehicles = Vehicle::query()->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))->get();

        foreach ($vehicles as $v) {
            foreach (['date_ct' => 'Contrôle technique', 'date_assurance' => 'Assurance'] as $field => $label) {
                if ($v->$field && $v->$field->between($from->startOfDay(), $to->endOfDay())) {
                    $events->push($this->event($v->$field, $field, "{$label} — {$v->immatriculation}", AlertLevel::forDays((int) today()->diffInDays($v->$field, false))));
                }
            }
            if ($v->km_vidange !== null) {
                $date = $this->estimateDateForKm($v, $v->km_vidange);
                if ($date && $date->between($from->startOfDay(), $to->endOfDay())) {
                    $events->push($this->event($date, 'vidange', "Vidange estimée — {$v->immatriculation} ({$v->km_vidange} km)", AlertLevel::forKm($v->km_vidange - $v->km_actuel)));
                }
            }
        }

        $vidanges = \App\Models\Vidange::with('vehicle:id,immatriculation')
            ->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->whereNotNull('prochaine_date')->whereBetween('prochaine_date', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($vidanges as $vd) {
            $events->push($this->event($vd->prochaine_date, 'revision', "Maintenance planifiée — {$vd->vehicle?->immatriculation} ({$vd->type})", AlertLevel::forDays((int) today()->diffInDays($vd->prochaine_date, false))));
        }

        return $events->sortBy('date')->values();
    }

    private function alert(string $type, AlertLevel $level, ?Vehicle $v, string $message): array
    {
        return [
            'type' => $type, 'level' => $level, 'district_id' => $v?->district_id, 'vehicle_id' => $v?->id,
            'message' => ($v ? "{$v->immatriculation} · " : '').$message,
        ];
    }

    private function event($date, string $type, string $label, AlertLevel $level): array
    {
        return ['date' => $date->format('Y-m-d'), 'type' => $type, 'label' => $label, 'level' => $level];
    }
}
