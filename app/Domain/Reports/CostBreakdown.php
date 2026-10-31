<?php

namespace App\Domain\Reports;

use App\Models\Expense;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;

/**
 * Coûts réels d'un mois par poste (carburant, maintenance, autres frais), par bailleur et par véhicule : sert au rapport par bailleur.
 *
 * Postes : carburant = ravitaillements ; maintenance = vidanges + immobilisations + frais de type « maintenance » ;
 * autres = frais hors carburant/maintenance (collation, hébergement, chargement…).
 * Le filtre « bailleur » retient les opérations des véhicules financés par ce bailleur.
 */
class CostBreakdown
{
    /**
     * Dépenses du mois par poste.
     *
     * @param  array<int, string>  $districtIds
     * @return array{carburant: float, maintenance: float, autres: float, global: float}
     */
    public function spent(array $districtIds, CarbonImmutable $month, ?string $bailleur = null): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $vehicleIds = $bailleur !== null && $bailleur !== ''
            ? Vehicle::whereIn('district_id', $districtIds)->where('bailleur', $bailleur)->pluck('id')->all()
            : null;
        $scope = fn ($q) => $vehicleIds === null ? $q : $q->whereIn('vehicle_id', $vehicleIds);

        $carburant = (float) $scope(Ravitaillement::whereIn('district_id', $districtIds)->whereDateBetween('date_ravitaillement', $from, $to))->get(['litres', 'prix_unitaire'])
            ->sum(fn ($r) => $r->litres * $r->prix_unitaire);
        $expenses = $scope(Expense::whereIn('district_id', $districtIds)->whereDateBetween('date_depense', $from, $to))->get(['type', 'montant']);
        $maintenance = (float) $scope(Vidange::whereIn('district_id', $districtIds)->whereDateBetween('date', $from, $to))->sum('montant')
            + (float) $scope(Immobilisation::whereIn('district_id', $districtIds)->whereDateBetween('date_debut', $from, $to))->sum('montant')
            + (float) $expenses->where('type', 'maintenance')->sum('montant');
        $autres = (float) $expenses->whereNotIn('type', ['carburant', 'maintenance'])->sum('montant');
        $carburant += (float) $expenses->where('type', 'carburant')->sum('montant');

        return ['carburant' => $carburant, 'maintenance' => $maintenance, 'autres' => $autres, 'global' => $carburant + $maintenance + $autres];
    }

    /**
     * Coûts du mois par véhicule (immatriculation), du plus élevé au plus faible.
     *
     * @param  array<int, string>  $districtIds
     * @return array<string, array{bailleur: string, carburant: float, maintenance: float, autres: float, total: float}>
     */
    public function spentByVehicle(array $districtIds, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $vehicles = Vehicle::whereIn('district_id', $districtIds)->get(['id', 'immatriculation', 'bailleur'])->keyBy('id');
        $rows = [];
        $add = function (?string $vehicleId, string $poste, float $amount) use (&$rows, $vehicles) {
            $key = $vehicleId && isset($vehicles[$vehicleId]) ? $vehicles[$vehicleId]->immatriculation : 'Non affecté';
            $rows[$key] ??= ['bailleur' => $vehicleId && isset($vehicles[$vehicleId]) ? (string) $vehicles[$vehicleId]->bailleur : '', 'carburant' => 0.0, 'maintenance' => 0.0, 'autres' => 0.0, 'total' => 0.0];
            $rows[$key][$poste] += $amount;
            $rows[$key]['total'] += $amount;
        };

        foreach (Ravitaillement::whereIn('district_id', $districtIds)->whereDateBetween('date_ravitaillement', $from, $to)->get(['vehicle_id', 'litres', 'prix_unitaire']) as $r) {
            $add($r->vehicle_id, 'carburant', (float) $r->litres * (float) $r->prix_unitaire);
        }
        foreach (Vidange::whereIn('district_id', $districtIds)->whereDateBetween('date', $from, $to)->get(['vehicle_id', 'montant']) as $v) {
            $add($v->vehicle_id, 'maintenance', (float) $v->montant);
        }
        foreach (Immobilisation::whereIn('district_id', $districtIds)->whereDateBetween('date_debut', $from, $to)->get(['vehicle_id', 'montant']) as $i) {
            $add($i->vehicle_id, 'maintenance', (float) $i->montant);
        }
        foreach (Expense::whereIn('district_id', $districtIds)->whereDateBetween('date_depense', $from, $to)->get(['vehicle_id', 'type', 'montant']) as $e) {
            $add($e->vehicle_id, match ($e->type) { 'carburant' => 'carburant', 'maintenance' => 'maintenance', default => 'autres' }, (float) $e->montant);
        }
        uasort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * Coûts du mois par bailleur (via les véhicules).
     *
     * @param  array<int, string>  $districtIds
     * @return array<int, array{bailleur: string, vehicules: int, carburant: float, maintenance: float, autres: float, total: float}>
     */
    public function byBailleur(array $districtIds, CarbonImmutable $month): array
    {
        $bailleurs = Vehicle::whereIn('district_id', $districtIds)->pluck('bailleur')->map(fn ($b) => (string) $b)->unique()->values();

        $rows = [];
        foreach ($bailleurs as $bailleur) {
            $spent = $this->spent($districtIds, $month, $bailleur === '' ? '__none__' : $bailleur);
            if ($bailleur === '') {
                // véhicules sans bailleur : tout ce qui n'est pas rattaché à un bailleur connu
                $known = $bailleurs->filter()->sum(fn ($b) => $this->spent($districtIds, $month, $b)['global']);
                $all = $this->spent($districtIds, $month)['global'];
                $spent = ['carburant' => 0.0, 'maintenance' => 0.0, 'autres' => 0.0, 'global' => max(0, $all - $known)];
            }
            $rows[] = [
                'bailleur' => $bailleur === '' ? 'Sans bailleur' : $bailleur,
                'vehicules' => Vehicle::whereIn('district_id', $districtIds)->where('bailleur', $bailleur === '' ? '' : $bailleur)->count()
                    + ($bailleur === '' ? Vehicle::whereIn('district_id', $districtIds)->whereNull('bailleur')->count() : 0),
                'carburant' => $spent['carburant'], 'maintenance' => $spent['maintenance'], 'autres' => $spent['autres'], 'total' => $spent['global'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }
}
