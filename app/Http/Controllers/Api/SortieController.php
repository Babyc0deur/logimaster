<?php

namespace App\Http\Controllers\Api;

use App\Models\Personnel;
use App\Models\SortieVehicule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SortieController extends CrudController
{
    protected string $model = SortieVehicule::class;

    protected string $permission = 'sorties';

    protected array $filters = ['statut', 'motif', 'vehicle_id', 'driver_id', 'circuit_id', 'chef_mission_id'];

    protected array $with = ['vehicle:id,immatriculation', 'driver:id,nom_complet', 'circuit:id,nom', 'chefMission:id,nom_complet', 'passagers:id,nom_complet'];

    protected string $orderBy = 'date_sortie';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';
        $request = request();

        return [
            'date_sortie' => ['sometimes', 'date'],
            'vehicle_id' => [$req, 'uuid', $this->existsInScope($request, 'vehicles')],
            'driver_id' => ['nullable', 'uuid', $this->existsInScope($request, 'drivers')],
            'chef_mission_id' => ['nullable', 'uuid', $this->existsInScope($request, 'personnels')],
            'passagers' => ['sometimes', 'array', 'max:3'],
            'passagers.*' => ['uuid', $this->existsInScope($request, 'personnels')],
            'circuit_id' => ['nullable', 'uuid', $this->existsInScope($request, 'circuits')],
            'point_depart' => ['nullable', 'string', 'max:160'],
            'point_arrivee' => ['nullable', 'string', 'max:160'],
            'etapes' => ['sometimes', 'array'],
            'etapes.*.lieu' => ['required_with:etapes', 'string', 'max:160'],
            'etapes.*.km' => ['nullable', 'integer', 'min:0'],
            'km_depart' => [$req, 'integer', 'min:0'],
            'km_arrivee' => ['nullable', 'integer', 'gt:'.($request->input('km_depart', $record?->km_depart ?? 0))],
            'motif' => [$req, Rule::in(array_keys(SortieVehicule::MOTIFS))],
            'destination' => ['nullable', 'string', 'max:160'],
            'circuit_respecte' => ['nullable', 'boolean'],
            'commentaires' => ['nullable', 'string'],
            // « validee » ne s'obtient que par POST /sorties/{id}/validate
            'statut' => ['sometimes', Rule::in(['planifiee', 'en_cours', 'terminee', 'annulee'])],
        ];
    }

    public function show(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_sorties');
        $sortie = $this->find($request, $id)->load($this->with);

        return $sortie->toArray() + [
            'distance' => $sortie->distance,
            'cout_carburant' => $sortie->cout_carburant,
            'cout_autres_frais' => $sortie->cout_autres_frais,
            'cout_total' => $sortie->cout_total,
            'consommation_reelle' => $sortie->consommation_reelle,
            'ecart_consommation_pct' => $sortie->ecart_consommation,
            'alerte_surconsommation' => $sortie->ecart_consommation !== null && $sortie->ecart_consommation > \App\Models\Setting::get('seuil_surconsommation_sortie'),
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = parent::store($request);
        $this->syncPassagers($request, SortieVehicule::findOrFail($response->getData()->id));

        return response()->json(SortieVehicule::with($this->with)->find($response->getData()->id), 201);
    }

    /** Une sortie clôturée (km d'arrivée renseigné) passe automatiquement à « terminee ». */
    public function update(Request $request, string $id)
    {
        abort_if($this->find($request, $id)->statut === 'validee', 422, 'Une sortie validée ne peut plus être modifiée.');
        if ($request->filled('km_arrivee') && ! $request->has('statut')) {
            $request->merge(['statut' => 'terminee']);
        }
        $sortie = parent::update($request, $id);
        $this->syncPassagers($request, $sortie);

        return $sortie->load($this->with);
    }

    /** Valide une sortie terminée (workflow superviseur). */
    public function validateSortie(Request $request, string $id)
    {
        $this->requirePermission($request, 'validate_sorties');
        $sortie = $this->find($request, $id);
        abort_unless($sortie->statut === 'terminee', 422, 'Seule une sortie terminée peut être validée.');
        $sortie->update(['statut' => 'validee', 'validated_at' => now(), 'validated_by' => $request->user()->getKey()]);

        return $sortie->fresh();
    }

    /** Duplique une sortie (nouvelle date, statut « planifiee », km de départ = km actuel du véhicule). */
    public function duplicate(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $this->requirePermission($request, 'create_sorties');
        $date = $request->validate(['date_sortie' => ['sometimes', 'date']])['date_sortie'] ?? today()->toDateString();
        $source = $this->find($request, $id);

        $copy = $source->replicate(['km_arrivee', 'statut', 'validated_at', 'validated_by', 'circuit_respecte', 'version'])
            ->fill(['date_sortie' => $date, 'statut' => 'planifiee', 'km_depart' => $source->vehicle->km_actuel ?? $source->km_depart]);
        $copy->save();
        $copy->passagers()->sync($source->passagers->pluck('id'));

        return response()->json($copy->load($this->with), 201);
    }

    private function syncPassagers(Request $request, SortieVehicule $sortie): void
    {
        if ($request->has('passagers')) {
            $ids = Personnel::where('district_id', $sortie->district_id)->whereIn('id', $request->input('passagers'))->pluck('id');
            $sortie->passagers()->sync($ids);
        }
    }
}
