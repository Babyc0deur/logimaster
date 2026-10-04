<?php

namespace App\Http\Controllers\Api;

use App\Models\Espc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EspcController extends CrudController
{
    protected string $model = Espc::class;

    protected string $permission = 'espc';

    protected array $filters = ['statut', 'type'];

    protected function rules(?Model $record): array
    {
        return [
            'nom' => [$record ? 'sometimes' : 'required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'max:40'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'responsable' => ['nullable', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:160'],
            'statut' => ['sometimes', Rule::in(['actif', 'inactif'])],
        ];
    }

    /** Suivi des livraisons du site : statut, date, lieu et raison pour chaque passage planifié. */
    public function deliveries(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_espc');
        $espc = $this->find($request, $id);

        return $espc->livraisons()->with('chronogramme:id,date_prevue,circuit_id,vehicle_id,statut')
            ->whereHas('chronogramme', fn ($q) => $q->where('statut', '!=', 'annulee'))
            ->latest('updated_at')->paginate($this->perPage($request));
    }
}
