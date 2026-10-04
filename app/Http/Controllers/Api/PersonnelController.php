<?php

namespace App\Http\Controllers\Api;

use App\Domain\Fleet\DriverStats;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Chefs de mission et passagers. */
class PersonnelController extends CrudController
{
    protected string $model = Personnel::class;

    protected string $permission = 'personnels';

    protected array $filters = ['fonction', 'statut'];

    protected string $orderBy = 'nom_complet';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'nom_complet' => [$req, 'string', 'max:120'],
            'fonction' => ['sometimes', Rule::in(array_keys(Personnel::FONCTIONS))],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'statut' => ['sometimes', Rule::in(['actif', 'inactif'])],
        ];
    }

    public function stats(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_personnels');

        return app(DriverStats::class)->forPersonnel($this->find($request, $id));
    }
}
