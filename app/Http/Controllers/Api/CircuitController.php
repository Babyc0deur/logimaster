<?php

namespace App\Http\Controllers\Api;

use App\Models\Circuit;
use App\Models\Espc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CircuitController extends CrudController
{
    protected string $model = Circuit::class;

    protected string $permission = 'circuits';

    protected array $filters = ['statut', 'frequence'];

    protected array $with = ['espc'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'nom' => [$req, 'string', 'max:120'],
            'distance_totale' => ['nullable', 'numeric', 'min:0'],
            'temps_estime_min' => ['nullable', 'integer', 'min:0'],
            'frequence' => ['nullable', Rule::in(['quotidien', 'hebdomadaire', 'bimensuel', 'mensuel', 'trimestriel'])],
            'statut' => ['sometimes', Rule::in(['actif', 'inactif'])],
            'espc' => ['sometimes', 'array'],
            'espc.*.id' => ['required_with:espc', 'uuid'],
            'espc.*.ordre' => ['required_with:espc', 'integer', 'min:1'],
            'espc.*.distance_km' => ['nullable', 'numeric', 'min:0'],
            'point_depart' => ['nullable', 'string', 'max:160'],
            'depart_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'depart_lon' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $response = parent::store($request);
        $circuit = Circuit::findOrFail($response->getData()->id);
        $this->syncEspc($request, $circuit);

        return response()->json($circuit->load('espc'), 201);
    }

    public function update(Request $request, string $id)
    {
        $circuit = parent::update($request, $id);
        $this->syncEspc($request, $circuit);

        return $circuit->fresh('espc');
    }

    /** Remplace la liste ordonnée des ESPC, limitée aux ESPC du même district. */
    private function syncEspc(Request $request, Circuit $circuit): void
    {
        if (! $request->has('espc')) {
            return;
        }
        $ids = collect($request->input('espc'))->pluck('id')->unique();
        abort_unless(
            Espc::where('district_id', $circuit->district_id)->whereIn('id', $ids)->count() === $ids->count(),
            422,
            'ESPC inconnu pour ce district.'
        );

        $circuit->espc()->sync(
            collect($request->input('espc'))->mapWithKeys(fn ($e) => [$e['id'] => ['ordre' => $e['ordre'], 'distance_km' => $e['distance_km'] ?? null]])->all()
        );
    }

    public function compliance(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_circuits');
        $circuit = $this->find($request, $id);

        $sorties = $circuit->sorties()->where('statut', '!=', 'annulee');
        $evaluated = (clone $sorties)->whereNotNull('circuit_respecte')->count();
        $respected = (clone $sorties)->where('circuit_respecte', true)->count();

        return [
            'circuit_id' => $circuit->id,
            'nb_sorties' => (clone $sorties)->count(),
            'evaluees' => $evaluated,
            'respectees' => $respected,
            'taux_respect' => $evaluated > 0 ? round($respected / $evaluated * 100, 2) : null,
        ];
    }
}
