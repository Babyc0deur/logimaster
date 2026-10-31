<?php

namespace App\Domain\Fleet;

use App\Models\Expense;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMutualisation;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Mutualisation d'un véhicule entre districts : qui peut voir l'activité d'un véhicule, et laquelle.
 *
 * - Le district propriétaire (celui du véhicule) voit toute l'activité de son véhicule, y compris chez le district utilisateur.
 * - Le district utilisateur voit l'activité du véhicule **pendant les périodes où il l'utilise** (celle des deux districts), rien d'autre :
 *   jamais les autres véhicules ni les autres données du propriétaire.
 * Chaque sortie, plein ou frais reste rattaché au district qui l'a saisi (ses coûts et ses indicateurs).
 */
class VehicleSharing
{
    public function isOwner(User $user, Vehicle $vehicle): bool
    {
        return $user->canAccessDistrict($vehicle->district_id);
    }

    /** Mises à disposition de ce véhicule au profit des districts de l'utilisateur. @return Collection<int, VehicleMutualisation> */
    public function borrowed(User $user, Vehicle $vehicle): Collection
    {
        $ids = $user->accessibleDistrictIds();

        return VehicleMutualisation::where('vehicle_id', $vehicle->id)->when($ids !== null, fn ($q) => $q->whereIn('district_id', $ids))->orderBy('date_debut')->get();
    }

    public function canView(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle) || $this->borrowed($user, $vehicle)->isNotEmpty();
    }

    /**
     * Périodes pendant lesquelles l'utilisateur voit l'activité : null = toute (propriétaire), sinon les périodes de mise à disposition.
     *
     * @return array<int, array{0: string, 1: string}>|null
     */
    public function visibleRanges(User $user, Vehicle $vehicle): ?array
    {
        if ($this->isOwner($user, $vehicle)) {
            return null;
        }

        return $this->borrowed($user, $vehicle)->map(fn (VehicleMutualisation $m) => [
            $m->date_debut->toDateString(),
            // une mise à disposition terminée à la main s'arrête à sa date de fin
            ($m->date_fin ?? CarbonImmutable::today())->toDateString(),
        ])->all();
    }

    /**
     * Activité du véhicule entre deux dates, limitée à ce que l'utilisateur a le droit de voir.
     *
     * @return array{sorties: Collection, pleins: Collection, frais: Collection, entretiens: Collection, totaux: array<string, array<string, mixed>>}
     */
    public function activity(User $user, Vehicle $vehicle, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ranges = $this->visibleRanges($user, $vehicle);
        $within = function (Builder $query, string $column) use ($from, $to, $ranges) {
            $query->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString());
            if ($ranges !== null) {
                $query->where(function (Builder $q) use ($column, $ranges) {
                    foreach ($ranges as [$start, $end]) {
                        $q->orWhere(fn (Builder $r) => $r->whereDate($column, '>=', $start)->whereDate($column, '<=', $end));
                    }
                    $ranges === [] && $q->whereRaw('1 = 0');
                });
            }

            return $query;
        };

        // Filament limite chaque modèle au district affiché : l'activité d'un véhicule mutualisé traverse les districts, on retire ce filtre
        $sorties = $within(SortieVehicule::withoutGlobalScopes()->with(['district:id,name', 'circuit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom')])->where('vehicle_id', $vehicle->id), 'date_sortie')->orderByDesc('date_sortie')->get();
        $pleins = $within(Ravitaillement::withoutGlobalScopes()->with('district:id,name')->where('vehicle_id', $vehicle->id), 'date_ravitaillement')->orderByDesc('date_ravitaillement')->get();
        $frais = $within(Expense::withoutGlobalScopes()->with('district:id,name')->where('vehicle_id', $vehicle->id), 'date_depense')->orderByDesc('date_depense')->get();
        $entretiens = collect()
            ->merge($within(Vidange::withoutGlobalScopes()->with('district:id,name')->where('vehicle_id', $vehicle->id), 'date')->get()->map(fn ($v) => [
                'date' => $v->date?->toDateString(), 'district' => $v->district?->name, 'libelle' => 'Vidange'.($v->km ? ' à '.number_format($v->km, 0, ',', ' ').' km' : ''), 'montant' => (float) $v->montant,
            ]))
            ->merge($within(Immobilisation::withoutGlobalScopes()->with('district:id,name')->where('vehicle_id', $vehicle->id), 'date_debut')->get()->map(fn ($i) => [
                'date' => $i->date_debut?->toDateString(), 'district' => $i->district?->name,
                'libelle' => 'Immobilisation : '.(Immobilisation::MOTIFS[$i->motif] ?? $i->motif), 'montant' => (float) $i->montant,
            ]))
            ->sortByDesc('date')->values();

        return ['sorties' => $sorties, 'pleins' => $pleins, 'frais' => $frais, 'entretiens' => $entretiens, 'totaux' => $this->totals($sorties, $pleins, $frais)];
    }

    /**
     * Totaux par district (celui qui a saisi) : sorties, kilomètres, carburant, autres frais.
     *
     * @return array<string, array{district: string, sorties: int, km: int, litres: float, carburant: float, frais: float, total: float}>
     */
    private function totals(Collection $sorties, Collection $pleins, Collection $frais): array
    {
        $rows = [];
        $row = function (?string $id, ?string $name) use (&$rows) {
            $key = $id ?? '-';
            $rows[$key] ??= ['district' => $name ?? '—', 'sorties' => 0, 'km' => 0, 'litres' => 0.0, 'carburant' => 0.0, 'frais' => 0.0, 'total' => 0.0];

            return $key;
        };
        foreach ($sorties as $s) {
            $k = $row($s->district_id, $s->district?->name);
            $rows[$k]['sorties']++;
            $rows[$k]['km'] += $s->km_arrivee !== null && $s->km_depart !== null ? max(0, (int) $s->km_arrivee - (int) $s->km_depart) : 0;
        }
        foreach ($pleins as $p) {
            $k = $row($p->district_id, $p->district?->name);
            $amount = (float) $p->litres * (float) $p->prix_unitaire;
            $rows[$k]['litres'] += (float) $p->litres;
            $rows[$k]['carburant'] += $amount;
            $rows[$k]['total'] += $amount;
        }
        foreach ($frais as $e) {
            if (in_array($e->type, ['carburant'], true)) {
                // un frais de type « carburant » est un plein saisi en frais : même poste que les pleins
                $k = $row($e->district_id, $e->district?->name);
                $rows[$k]['carburant'] += (float) $e->montant;
                $rows[$k]['total'] += (float) $e->montant;

                continue;
            }
            $k = $row($e->district_id, $e->district?->name);
            $rows[$k]['frais'] += (float) $e->montant;
            $rows[$k]['total'] += (float) $e->montant;
        }
        uasort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }
}
