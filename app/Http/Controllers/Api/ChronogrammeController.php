<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chronogramme\ChronogrammeWorkflow;
use App\Models\Chronogramme;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChronogrammeController extends CrudController
{
    protected string $model = Chronogramme::class;

    protected string $permission = 'chronogrammes';

    protected array $filters = ['statut', 'vehicle_id', 'driver_id', 'circuit_id', 'motif'];

    protected array $with = ['vehicle:id,immatriculation', 'driver:id,nom_complet', 'circuit:id,nom', 'espc:id,nom'];

    protected string $orderBy = 'date_prevue';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';
        $request = request();

        return [
            'date_prevue' => [$req, 'date'],
            'heure_depart' => ['nullable', 'date_format:H:i'],
            'vehicle_id' => ['nullable', 'uuid', $this->existsInScope($request, 'vehicles')],
            'driver_id' => ['nullable', 'uuid', $this->existsInScope($request, 'drivers')],
            'circuit_id' => ['nullable', 'uuid', $this->existsInScope($request, 'circuits')],
            'motif' => ['sometimes', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:160'],
            'statut' => ['sometimes', Rule::in(array_keys(Chronogramme::STATUTS))],
            'commentaires' => ['nullable', 'string'],
        ];
    }

    /** Liste filtrable par période : ?from=YYYY-MM-DD&to=YYYY-MM-DD. */
    public function index(Request $request)
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $this->requirePermission($request, 'view_chronogrammes');

        $query = $this->scoped($request, Chronogramme::query()->with($this->with))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date_prevue', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date_prevue', '<=', $d));
        foreach ($this->filters as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->query($filter)));
        }

        return $query->orderBy('date_prevue')->orderBy('heure_depart')->paginate($this->perPage($request));
    }

    /** Démarre la sortie prévue : crée la sortie, la relie au planning et marque l'entrée « réalisée ». */
    public function start(Request $request, string $id)
    {
        $this->requirePermission($request, 'update_chronogrammes');
        $this->requirePermission($request, 'create_sorties');
        $plan = $this->find($request, $id);

        return response()->json(['chronogramme' => $plan->fresh(), 'sortie' => $plan->demarrer()], 201);
    }

    /** Soumet à validation le planning d'un mois : { district_id, month: YYYY-MM, ids?: [] }. */
    public function submit(Request $request)
    {
        return $this->workflow($request, 'update_chronogrammes', 'submit');
    }

    public function validatePlanning(Request $request)
    {
        return $this->workflow($request, 'validate_chronogrammes', 'validate');
    }

    /** Refus avec motif obligatoire. */
    public function refuse(Request $request)
    {
        return $this->workflow($request, 'validate_chronogrammes', 'refuse', true);
    }

    /** Lève la validation (retour en brouillon). */
    public function reopen(Request $request)
    {
        return $this->workflow($request, 'validate_chronogrammes', 'reopen');
    }

    private function workflow(Request $request, string $permission, string $method, bool $withMotif = false)
    {
        $this->requirePermission($request, $permission);
        $data = $request->validate([
            'district_id' => ['required', 'uuid'],
            'month' => ['required', 'date_format:Y-m'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['uuid'],
            'motif' => [$withMotif ? 'required' : 'nullable', 'string', 'max:500'],
        ]);
        $this->assertDistrictAccess($request, $data['district_id']);

        $args = [$data['district_id'], CarbonImmutable::createFromFormat('Y-m', $data['month'])->startOfMonth(), $request->user()];
        $withMotif && $args[] = $data['motif'];
        $args[] = $data['ids'] ?? null;
        $count = app(ChronogrammeWorkflow::class)->{$method}(...$args);

        return response()->json(['count' => $count]);
    }
}
