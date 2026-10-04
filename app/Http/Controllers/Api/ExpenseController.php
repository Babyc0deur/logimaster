<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ExpenseController extends CrudController
{
    protected string $model = Expense::class;

    protected string $permission = 'expenses';

    protected array $filters = ['type', 'sortie_id'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'sortie_id' => ['nullable', 'uuid', $this->existsInScope(request(), 'sorties_vehicules')],
            'type' => [$req, Rule::in(['collation', 'chargement', 'dechargement', 'hebergement', 'peage', 'carburant', 'maintenance', 'autre'])],
            'date_depense' => ['sometimes', 'date'],
            'vehicle_id' => ['nullable', 'uuid', $this->existsInScope(request(), 'vehicles')],
            'motif' => ['nullable', 'string', 'max:50'],
            'beneficiaire' => ['nullable', 'string', 'max:120'],
            'montant' => [$req, 'numeric', 'min:0'],
            'commentaire' => ['nullable', 'string'],
        ];
    }
}
