<?php

namespace App\Domain\Fuel;

use App\Models\Ravitaillement;
use App\Models\Setting;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Analyse de la consommation de carburant : réel vs théorique, anomalies, séries mensuelles. */
class FuelAnalyzer
{
    /**
     * Consommation par véhicule sur une période.
     *
     * @param  array<int, string>|null  $districtIds
     * @return Collection<int, array<string, mixed>>
     */
    public function perVehicle(?array $districtIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $seuil = (float) Setting::get('seuil_surconsommation');

        $litres = Ravitaillement::query()->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->whereDateBetween('date_ravitaillement', $from, $to)
            ->selectRaw('vehicle_id, SUM(litres) as litres, SUM(litres * prix_unitaire) as cout')->groupBy('vehicle_id')->get()->keyBy('vehicle_id');
        $km = SortieVehicule::query()->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->whereNotNull('km_arrivee')->where('statut', '!=', 'annulee')->whereDateBetween('date_sortie', $from, $to)
            ->selectRaw('vehicle_id, SUM(km_arrivee - km_depart) as km')->groupBy('vehicle_id')->get()->keyBy('vehicle_id');

        return Vehicle::query()->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->orderBy('immatriculation')->get()->map(function (Vehicle $v) use ($litres, $km, $seuil) {
                $l = (float) ($litres[$v->id]->litres ?? 0);
                $cout = (float) ($litres[$v->id]->cout ?? 0);
                $d = (float) ($km[$v->id]->km ?? 0);
                $reelle = $d > 0 && $l > 0 ? round($l / $d * 100, 2) : null;
                $theorique = $v->consommation_theorique !== null ? (float) $v->consommation_theorique : null;
                $ecart = $reelle !== null && $theorique ? round(($reelle - $theorique) / $theorique * 100, 1) : null;

                return [
                    'vehicle' => $v, 'litres' => $l, 'cout' => $cout, 'km' => $d,
                    'reelle' => $reelle, 'theorique' => $theorique, 'ecart' => $ecart,
                    'cout_km' => $d > 0 ? round($cout / $d, 1) : null,
                    'surconsommation' => $ecart !== null && $ecart > $seuil,
                ];
            });
    }

    /**
     * Série mensuelle sur $months mois : litres, coût, km, consommation réelle et théorique pondérée.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthlySeries(?array $districtIds, int $months = 12): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            $rows = $this->perVehicle($districtIds, $start, $start->endOfMonth());
            $km = $rows->sum('km');
            $litres = $rows->sum('litres');
            $theoLitres = $rows->sum(fn ($r) => ($r['theorique'] ?? 0) * $r['km'] / 100);
            $series[] = [
                'period' => $start->format('Y-m'), 'label' => $start->translatedFormat('M y'),
                'litres' => round($litres, 2), 'cout' => round($rows->sum('cout')), 'km' => $km,
                'reelle' => $km > 0 ? round($litres / $km * 100, 2) : null,
                'theorique' => $km > 0 ? round($theoLitres / $km * 100, 2) : null,
                'cout_km' => $km > 0 ? round($rows->sum('cout') / $km, 1) : null,
            ];
        }

        return $series;
    }

    /** Répartition litres / coût par motif de sortie sur la période. */
    public function byMotif(?array $districtIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Ravitaillement::with('sortie:id,motif')
            ->when($districtIds !== null, fn ($q) => $q->whereIn('district_id', $districtIds))
            ->whereDateBetween('date_ravitaillement', $from, $to)->get()
            ->groupBy(fn ($r) => $r->motif ?? $r->sortie?->motif ?? 'non_affecte')
            ->map(fn ($g) => ['litres' => round((float) $g->sum('litres'), 2), 'cout' => round((float) $g->sum(fn ($r) => $r->litres * $r->prix_unitaire))]);
    }

    /**
     * Anomalie probable d'un ravitaillement : compteur incohérent, ou consommation depuis le plein
     * précédent supérieure au seuil par rapport à la consommation théorique.
     */
    public function detectAnomaly(Ravitaillement $r): ?string
    {
        if ($r->km_compteur === null) {
            return null;
        }
        $previous = Ravitaillement::where('vehicle_id', $r->vehicle_id)->whereNotNull('km_compteur')
            ->when($r->exists, fn ($q) => $q->whereKeyNot($r->getKey()))
            ->whereDate('date_ravitaillement', '<=', $r->date_ravitaillement ?? today())
            ->where(fn ($q) => $q->whereDate('date_ravitaillement', '<', $r->date_ravitaillement ?? today())->orWhere('created_at', '<=', $r->created_at ?? now()))
            ->orderByDesc('date_ravitaillement')->orderByDesc('created_at')->first();
        if (! $previous) {
            return null;
        }
        $distance = $r->km_compteur - $previous->km_compteur;
        if ($distance <= 0) {
            return 'km_incoherent';
        }
        $theorique = (float) (Vehicle::whereKey($r->vehicle_id)->value('consommation_theorique') ?? 0);
        $reelle = (float) $r->litres / $distance * 100;
        if ($theorique > 0 && $reelle > $theorique * (1 + (float) Setting::get('seuil_surconsommation') / 100)) {
            return 'surconsommation';
        }

        return null;
    }

    /** Projection de fin de mois du coût et des litres, d'après le rythme depuis le 1er. */
    public function projection(?array $districtIds): array
    {
        $now = CarbonImmutable::now();
        $rows = $this->perVehicle($districtIds, $now->startOfMonth(), $now);
        $elapsed = max(1, $now->day);
        $factor = $now->daysInMonth / $elapsed;

        return ['litres' => round($rows->sum('litres') * $factor), 'cout' => round($rows->sum('cout') * $factor)];
    }
}
