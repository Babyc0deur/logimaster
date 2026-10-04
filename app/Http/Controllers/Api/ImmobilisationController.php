<?php

namespace App\Http\Controllers\Api;

use App\Models\Immobilisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ImmobilisationController extends CrudController
{
    protected string $model = Immobilisation::class;

    protected string $permission = 'immobilisations';

    protected array $filters = ['vehicle_id', 'statut', 'motif'];

    protected array $with = ['vehicle:id,immatriculation'];

    protected string $orderBy = 'date_debut';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'vehicle_id' => [$req, 'uuid', $this->existsInScope(request(), 'vehicles')],
            'date_debut' => [$req, 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'motif' => [$req, Rule::in(array_keys(\App\Models\Immobilisation::MOTIFS))],
            'description' => ['nullable', 'string'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'prestataire' => ['nullable', 'string', 'max:120'],
            'numero_facture' => ['nullable', 'string', 'max:60'],
            'statut' => ['sometimes', Rule::in(array_keys(\App\Models\Immobilisation::STATUTS))],
        ];
    }
}
