<?php

namespace App\Http\Controllers\Api;

use App\Models\Ravitaillement;
use Illuminate\Database\Eloquent\Model;

class RavitaillementController extends CrudController
{
    protected string $model = Ravitaillement::class;

    protected string $permission = 'ravitaillements';

    protected array $filters = ['vehicle_id', 'driver_id', 'sortie_id'];

    protected array $with = ['vehicle:id,immatriculation'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';
        $request = request();

        return [
            'vehicle_id' => [$req, 'uuid', $this->existsInScope($request, 'vehicles')],
            'sortie_id' => ['nullable', 'uuid', $this->existsInScope($request, 'sorties_vehicules')],
            'driver_id' => ['nullable', 'uuid', $this->existsInScope($request, 'drivers')],
            'litres' => [$req, 'numeric', 'gt:0'],
            'prix_unitaire' => [$req, 'numeric', 'gt:0'],
            'station' => ['nullable', 'string', 'max:120'],
            'numero_facture' => ['nullable', 'string', 'max:60'],
            'km_compteur' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Validation par le gestionnaire. */
    public function validateRavitaillement(\Illuminate\Http\Request $request, string $id)
    {
        $this->requirePermission($request, 'validate_ravitaillements');
        $ravitaillement = $this->find($request, $id);
        $ravitaillement->update(['valide_at' => now(), 'valide_par' => $request->user()->getKey()]);

        return $ravitaillement->fresh();
    }
}
