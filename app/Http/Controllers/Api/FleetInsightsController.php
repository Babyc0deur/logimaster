<?php

namespace App\Http\Controllers\Api;

use App\Domain\Fleet\MaintenancePlanner;
use App\Domain\Fuel\FuelAnalyzer;
use App\Models\FuelPrice;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Carburant (analyse, prix, seuils) et calendrier de maintenance. */
class FleetInsightsController extends ApiController
{
    /** Analyse de consommation d'un mois : par véhicule, par motif, série 12 mois, projection. */
    public function fuelAnalysis(Request $request, FuelAnalyzer $analyzer)
    {
        $this->requirePermission($request, 'view_ravitaillements');
        $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $ids = $this->requestedDistrictIds($request);
        $month = $request->filled('period')
            ? CarbonImmutable::createFromFormat('Y-m', $request->query('period'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $vehicles = $analyzer->perVehicle($ids, $month, $month->endOfMonth());

        return [
            'period' => $month->format('Y-m'),
            'seuil_surconsommation_pct' => Setting::get('seuil_surconsommation'),
            'totaux' => [
                'litres' => round($vehicles->sum('litres'), 2), 'cout' => round($vehicles->sum('cout')), 'km' => $vehicles->sum('km'),
                'vehicules_en_surconsommation' => $vehicles->where('surconsommation', true)->count(),
            ],
            'vehicules' => $vehicles->map(fn ($r) => ['vehicle_id' => $r['vehicle']->id, 'immatriculation' => $r['vehicle']->immatriculation] + collect($r)->except('vehicle')->all())->values(),
            'par_motif' => $analyzer->byMotif($ids, $month, $month->endOfMonth()),
            'serie_mensuelle' => $analyzer->monthlySeries($ids, 12),
            'projection_fin_de_mois' => $month->isSameMonth(now()) ? $analyzer->projection($ids) : null,
        ];
    }

    public function fuelPrices(Request $request)
    {
        $this->requirePermission($request, 'view_ravitaillements');

        return [
            'en_vigueur' => collect(array_keys(FuelPrice::TYPES))->mapWithKeys(fn ($t) => [$t => FuelPrice::current($t)]),
            'historique' => FuelPrice::orderByDesc('date_effet')->get(),
        ];
    }

    public function storeFuelPrice(Request $request)
    {
        $this->requirePermission($request, 'manage_settings');
        $data = $request->validate([
            'type_carburant' => ['required', Rule::in(array_keys(FuelPrice::TYPES))],
            'prix' => ['required', 'numeric', 'min:1'],
            'date_effet' => ['required', 'date'],
        ]);

        return response()->json(FuelPrice::create($data), 201);
    }

    public function settings(Request $request)
    {
        $this->requirePermission($request, 'view_dashboard');

        return collect(Setting::DEFAULTS)->map(fn ($d, $key) => Setting::get($key));
    }

    public function updateSettings(Request $request)
    {
        $this->requirePermission($request, 'manage_settings');
        $data = $request->validate(collect(Setting::DEFAULTS)->mapWithKeys(fn ($d, $k) => [$k => ['sometimes', 'numeric', 'min:0']])->all());
        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return $this->settings($request);
    }

    /** Événements de maintenance entre deux dates (défaut : mois courant) avec niveau d'alerte. */
    public function maintenanceCalendar(Request $request, MaintenancePlanner $planner)
    {
        $this->requirePermission($request, 'view_vidanges');
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($request->query('from', now()->startOfMonth()));
        $to = CarbonImmutable::parse($request->query('to', $from->endOfMonth()));

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'events' => $planner->events($this->requestedDistrictIds($request), $from, $to)
                ->map(fn ($e) => ['level' => $e['level']->value] + $e)->values(),
        ];
    }
}
