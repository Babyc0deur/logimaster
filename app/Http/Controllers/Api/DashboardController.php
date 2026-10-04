<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use App\Models\Driver;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function index(Request $request)
    {
        $this->requirePermission($request, 'view_dashboard');
        $ids = $this->requestedDistrictIds($request);
        $in = fn (Builder $q) => $ids === null ? $q : $q->whereIn('district_id', $ids);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $fuel = $in(Ravitaillement::query())->whereDateBetween('date_ravitaillement', $from, $to);

        return [
            'vehicules' => [
                'total' => $in(Vehicle::query())->count(),
                'par_statut' => $in(Vehicle::query())->selectRaw('statut, count(*) as n')->groupBy('statut')->pluck('n', 'statut'),
            ],
            'sorties_en_cours' => $in(SortieVehicule::query())->where('statut', 'en_cours')->count(),
            'km_mois' => (float) $in(SortieVehicule::query())->whereNotNull('km_arrivee')->whereDateBetween('date_sortie', $from, $to)
                ->sum(DB::raw('km_arrivee - km_depart')),
            'carburant_mois' => [
                'litres' => (float) (clone $fuel)->sum('litres'),
                'montant' => (float) (clone $fuel)->sum(DB::raw('litres * prix_unitaire')),
            ],
            'immobilisations_en_cours' => $in(Immobilisation::query())->where('statut', 'en_cours')->count(),
            'alertes' => count($this->alertsFor($ids)),
        ];
    }

    public function alerts(Request $request)
    {
        $this->requirePermission($request, 'view_dashboard');

        return $this->alertsFor($this->requestedDistrictIds($request));
    }

    public function missionsToday(Request $request)
    {
        $this->requirePermission($request, 'view_dashboard');
        $ids = $this->requestedDistrictIds($request);

        return SortieVehicule::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('district_id', $ids))
            ->where(fn ($q) => $q->where('statut', 'en_cours')->orWhereDate('date_sortie', today()))
            ->with(['vehicle:id,immatriculation', 'driver:id,nom_complet', 'circuit:id,nom'])
            ->latest()->limit(100)->get();
    }

    /**
     * Alertes triées par gravité (vidanges, CT, assurances, documents, immobilisations > 7 jours),
     * niveaux 🔴 urgent / 🟠 attention définis par les seuils des paramètres.
     *
     * @param  array<int, string>|null  $ids
     * @return array<int, array<string, mixed>>
     */
    private function alertsFor(?array $ids): array
    {
        return \App\Domain\Fleet\AlertCenter::all($ids)->map(fn ($a) => [
            'type' => $a['type'],
            'level' => $a['level']->value,
            'severity' => $a['level']->color(),
            'district_id' => $a['district_id'],
            'vehicle_id' => $a['vehicle_id'],
            'message' => $a['message'],
        ] + (isset($a['document_id']) ? ['document_id' => $a['document_id']] : []) + (isset($a['budget_id']) ? ['budget_id' => $a['budget_id']] : []))->all();
    }
}
