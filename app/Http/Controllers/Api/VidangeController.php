<?php

namespace App\Http\Controllers\Api;

use App\Models\Vidange;
use Illuminate\Database\Eloquent\Model;

class VidangeController extends CrudController
{
    protected string $model = Vidange::class;

    protected string $permission = 'vidanges';

    protected array $filters = ['vehicle_id', 'type'];

    protected array $with = ['vehicle:id,immatriculation'];

    protected string $orderBy = 'date';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'vehicle_id' => [$req, 'uuid', $this->existsInScope(request(), 'vehicles')],
            'date' => [$req, 'date'],
            'km' => [$req, 'integer', 'min:0'],
            'type' => ['nullable', 'in:simple,complete,revision'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'prestataire' => ['nullable', 'string', 'max:120'],
            'numero_facture' => ['nullable', 'string', 'max:60'],
            'prochain_km' => ['nullable', 'integer', 'min:0'],
            'prochaine_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
