<?php

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends CrudController
{
    protected string $model = Driver::class;

    protected string $permission = 'drivers';

    protected array $filters = ['statut'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'matricule' => [$req, 'string', 'max:30', Rule::unique('drivers', 'matricule')->ignore($record?->id)],
            'nom_complet' => [$req, 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'categorie_permis' => ['nullable', 'string', 'max:10'],
            'permis_expiration' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:160'],
            'numero_permis' => ['nullable', 'string', 'max:40'],
            'date_obtention_permis' => ['nullable', 'date', 'before_or_equal:today'],
            'vehicule_principal_id' => ['nullable', 'uuid', $this->existsInScope(request(), 'vehicles')],
            'statut' => ['sometimes', Rule::in(array_keys(Driver::STATUTS))],
        ];
    }

    public function stats(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_drivers');

        return app(\App\Domain\Fleet\DriverStats::class)->forDriver($this->find($request, $id));
    }

    public function licenseStatus(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_drivers');
        $driver = $this->find($request, $id);

        $days = $driver->permis_expiration ? (int) now()->startOfDay()->diffInDays($driver->permis_expiration, false) : null;

        return [
            'driver_id' => $driver->id,
            'categorie_permis' => $driver->categorie_permis,
            'permis_expiration' => $driver->permis_expiration?->toDateString(),
            'jours_restants' => $days,
            'statut' => match (true) {
                $days === null => 'inconnu',
                $days < 0 => 'expire',
                $days <= 30 => 'expire_bientot',
                default => 'valide',
            },
        ];
    }
}
