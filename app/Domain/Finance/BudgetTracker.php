<?php

namespace App\Domain\Finance;

use App\Domain\Fleet\AlertLevel;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Suivi budgétaire : dépenses réelles par poste (carburant, maintenance, autres frais) et par bailleur,
 * écart au budget alloué, prévision de fin de mois, alertes de dépassement.
 *
 * Postes : carburant = ravitaillements ; maintenance = vidanges + immobilisations + frais de type « maintenance » ;
 * autres = frais hors carburant/maintenance (collation, hébergement, chargement…).
 * Le filtre « bailleur » retient les opérations des véhicules financés par ce bailleur.
 */
class BudgetTracker
{
    public const POSTES = ['carburant', 'maintenance', 'autres'];

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
     * Situation d'un budget (alloué, dépensé, reste, %, prévision de fin de mois, niveau d'alerte).
     *
     * @return array<string, mixed>
     */
    public function status(Budget $budget, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $month = CarbonImmutable::parse($budget->period)->startOfMonth();
        $spent = $this->spent([$budget->district_id], $month, $budget->bailleur)[$budget->poste] ?? 0.0;
        $alloue = (float) $budget->montant_alloue;

        // Mois en cours : on extrapole au rythme observé depuis le 1er ; mois passé : le réel ; mois futur : 0.
        $forecast = match (true) {
            $month->isSameMonth($today) => $spent / max(1, $today->day) * $month->daysInMonth,
            $month->isFuture() => 0.0,
            default => $spent,
        };
        $pct = $alloue > 0 ? $spent / $alloue * 100 : null;

        return [
            'alloue' => $alloue, 'depense' => $spent, 'reste' => $alloue - $spent, 'pct' => $pct,
            'prevision' => round($forecast), 'ecart_prevision' => round($forecast - $alloue),
            'niveau' => match (true) {
                $pct !== null && $pct > 100 => 'depasse',
                $alloue > 0 && $forecast > $alloue * 1.0 && $month->isSameMonth($today) => 'prevision_depassement',
                $pct !== null && $pct >= 80 => 'attention',
                default => 'ok',
            },
        ];
    }

    /**
     * Synthèse du mois pour un périmètre : budget alloué (global du district s'il existe, sinon somme des postes),
     * dépenses, reste, prévision de fin de mois, et répartition alloué/dépensé par poste.
     *
     * @param  array<int, string>  $districtIds
     * @return array<string, mixed>
     */
    public function monthSummary(array $districtIds, CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $spent = $this->spent($districtIds, $month);
        $budgets = Budget::whereIn('district_id', $districtIds)->whereDate('period', $month->startOfMonth()->toDateString())->where('bailleur', '')->get();

        $perPoste = ['carburant' => 0.0, 'maintenance' => 0.0, 'autres' => 0.0, 'global' => 0.0];
        foreach ($budgets as $b) {
            $perPoste[$b->poste] += (float) $b->montant_alloue;
        }
        $total = 0.0;
        foreach ($budgets->groupBy('district_id') as $rows) {
            $global = (float) $rows->where('poste', 'global')->sum('montant_alloue');
            $total += $global > 0 ? $global : (float) $rows->where('poste', '!=', 'global')->sum('montant_alloue');
        }

        $forecast = match (true) {
            $month->isSameMonth($today) => $spent['global'] / max(1, $today->day) * $month->daysInMonth,
            $month->isFuture() => 0.0,
            default => $spent['global'],
        };

        return [
            'alloue' => $total, 'depense' => $spent['global'], 'reste' => $total - $spent['global'],
            'pct' => $total > 0 ? $spent['global'] / $total * 100 : null, 'prevision' => round($forecast),
            'postes' => collect(['carburant', 'maintenance', 'autres'])->mapWithKeys(fn ($p) => [$p => ['alloue' => $perPoste[$p], 'depense' => $spent[$p]]])->all(),
            'alertes' => $month->isSameMonth($today) ? $this->alerts($districtIds, $today)->count() : 0,
        ];
    }

    /**
     * Dépenses du mois par véhicule (carburant, maintenance, autres) ; les frais sans véhicule sont regroupés sous « Non affecté ».
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
     * Alertes de dépassement des budgets du mois en cours.
     *
     * @param  array<int, string>|null  $districtIds
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(?array $districtIds, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today();

        return Budget::with('district:id,name')->whereDate('period', $today->startOfMonth()->toDateString())
            ->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))->get()
            ->map(function (Budget $b) use ($today) {
                $s = $this->status($b, $today);
                if ($s['niveau'] === 'ok') {
                    return null;
                }
                $label = Budget::POSTES[$b->poste].($b->bailleur ? " ({$b->bailleur})" : '');
                $message = match ($s['niveau']) {
                    'depasse' => "Budget {$label} dépassé : ".number_format($s['depense'], 0, ',', ' ').' / '.number_format($s['alloue'], 0, ',', ' ').' FCFA ('.round($s['pct']).' %)',
                    'prevision_depassement' => "Budget {$label} : dépassement prévu en fin de mois (".number_format($s['prevision'], 0, ',', ' ').' FCFA prévus pour '.number_format($s['alloue'], 0, ',', ' ').' alloués)',
                    default => "Budget {$label} consommé à ".round($s['pct']).' %',
                };

                return [
                    'type' => 'budget', 'level' => $s['niveau'] === 'attention' ? AlertLevel::Attention : AlertLevel::Urgent,
                    'district_id' => $b->district_id, 'vehicle_id' => null, 'budget_id' => $b->id,
                    'message' => ($b->district?->name ? "{$b->district->name} · " : '').$message,
                ];
            })->filter()->values();
    }

    /**
     * Coûts du mois par bailleur (via les véhicules), avec le budget alloué à ce bailleur.
     *
     * @param  array<int, string>  $districtIds
     * @return array<int, array<string, mixed>>
     */
    public function byBailleur(array $districtIds, CarbonImmutable $month): array
    {
        $bailleurs = Vehicle::whereIn('district_id', $districtIds)->pluck('bailleur')->map(fn ($b) => (string) $b)->unique()->values();
        $budgets = Budget::whereIn('district_id', $districtIds)->whereDate('period', $month->startOfMonth()->toDateString())->where('bailleur', '!=', '')->get()->groupBy('bailleur');

        $rows = [];
        foreach ($bailleurs as $bailleur) {
            $spent = $this->spent($districtIds, $month, $bailleur === '' ? '__none__' : $bailleur);
            if ($bailleur === '') {
                // véhicules sans bailleur : tout ce qui n'est pas rattaché à un bailleur connu
                $known = $bailleurs->filter()->sum(fn ($b) => $this->spent($districtIds, $month, $b)['global']);
                $all = $this->spent($districtIds, $month)['global'];
                $spent = ['carburant' => 0.0, 'maintenance' => 0.0, 'autres' => 0.0, 'global' => max(0, $all - $known)];
            }
            $alloue = (float) ($budgets[$bailleur] ?? collect())->sum('montant_alloue');
            $rows[] = [
                'bailleur' => $bailleur === '' ? 'Sans bailleur' : $bailleur,
                'vehicules' => Vehicle::whereIn('district_id', $districtIds)->where('bailleur', $bailleur === '' ? '' : $bailleur)->count()
                    + ($bailleur === '' ? Vehicle::whereIn('district_id', $districtIds)->whereNull('bailleur')->count() : 0),
                'carburant' => $spent['carburant'], 'maintenance' => $spent['maintenance'], 'autres' => $spent['autres'], 'total' => $spent['global'],
                'alloue' => $alloue, 'pct' => $alloue > 0 ? $spent['global'] / $alloue * 100 : null,
            ];
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }
}
