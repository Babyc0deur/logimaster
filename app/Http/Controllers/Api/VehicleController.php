<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehicleController extends CrudController
{
    protected string $model = Vehicle::class;

    protected string $permission = 'vehicles';

    protected array $filters = ['statut', 'type_carburant', 'type_vehicule'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'immatriculation' => [$req, 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{2,19}$/', Rule::unique('vehicles', 'immatriculation')->ignore($record?->id)],
            'marque' => ['nullable', 'string', 'max:60'],
            'modele' => ['nullable', 'string', 'max:60'],
            'type_vehicule' => ['nullable', 'string', 'max:40'],
            'type_carburant' => ['sometimes', Rule::in(['essence', 'diesel', 'hybride'])],
            'consommation_theorique' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'km_actuel' => ['sometimes', 'integer', 'min:0'],
            'km_vidange' => ['nullable', 'integer', 'min:0'],
            'annee_circulation' => ['nullable', 'integer', 'min:1980', 'max:'.date('Y')],
            'poids_vide' => ['nullable', 'integer', 'min:0'],
            'capacite_charge' => ['nullable', 'integer', 'min:0'],
            'volume_utile' => ['nullable', 'numeric', 'min:0'],
            'appartenance' => ['sometimes', Rule::in(array_keys(Vehicle::APPARTENANCES))],
            'bailleur' => ['nullable', 'string', 'max:120'],
            'date_reception' => ['nullable', 'date', 'before_or_equal:today'],
            'prix_carburant' => ['nullable', 'numeric', 'min:0'],
            'date_dernier_releve' => ['nullable', 'date'],
            'date_ct' => ['nullable', 'date'],
            'date_assurance' => ['nullable', 'date'],
            'statut' => ['sometimes', Rule::in(['disponible', 'en_mission', 'en_maintenance', 'hors_service'])],
        ];
    }

    public function documents(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_documents');

        return $this->find($request, $id)->documents()->latest()->get();
    }

    public function outings(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_sorties');

        return $this->find($request, $id)->sorties()->with(['driver:id,nom_complet', 'circuit:id,nom'])
            ->latest()->paginate($this->perPage($request));
    }

    public function maintenance(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_vidanges');
        $vehicle = $this->find($request, $id);

        return [
            'vidanges' => $vehicle->vidanges()->orderByDesc('date')->get(),
            'immobilisations' => $vehicle->immobilisations()->orderByDesc('date_debut')->get(),
            'vidange_imminente' => $vehicle->vidangeImminente(),
        ];
    }

    public function stats(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_vehicles');
        $v = $this->find($request, $id);

        $stats = app(\App\Domain\Fleet\VehicleStats::class);
        $total = $stats->forPeriod($v, \Carbon\CarbonImmutable::parse('2000-01-01'), \Carbon\CarbonImmutable::now());
        $recent = $stats->forPeriod($v, \Carbon\CarbonImmutable::now()->subDays(29), \Carbon\CarbonImmutable::now());
        $km = $v->getKmParcourus();
        $litres = (float) $v->ravitaillements()->sum('litres');
        $cout = (float) $v->ravitaillements()->sum(DB::raw('litres * prix_unitaire'));

        return [
            'vehicle_id' => $v->id,
            'nb_sorties' => $v->sorties()->count(),
            'km_parcourus' => $km,
            'litres' => $litres,
            'cout_carburant' => $cout,
            'consommation_reelle_l_100km' => $km > 0 ? round($litres / $km * 100, 2) : null,
            'consommation_theorique_l_100km' => $v->consommation_theorique !== null ? (float) $v->consommation_theorique : null,
            'cout_maintenance' => (float) $v->vidanges()->sum('montant') + (float) $v->immobilisations()->sum('montant'),
            'nb_immobilisations' => $v->immobilisations()->count(),
            'taux_utilisation_30j' => $recent['taux_utilisation'],
            'taux_disponibilite_30j' => $recent['taux_disponibilite'],
            'cout_km' => $total['cout_km'],
        ];
    }
}
