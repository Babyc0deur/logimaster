<?php

namespace App\Http\Controllers\Api;

use App\Models\Budget;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetController extends CrudController
{
    protected string $model = Budget::class;

    protected string $permission = 'budgets';

    protected string $orderBy = 'period';

    protected function rules(?Model $record): array
    {
        $districtId = $record?->district_id ?? request()->input('district_id');
        $req = $record ? 'sometimes' : 'required';
        // « bailleur » vide = budget tous bailleurs (stocké '').
        $poste = request()->input('poste', $record?->poste ?? 'global');
        $bailleur = request()->input('bailleur', $record?->bailleur ?? '') ?? '';

        return [
            'period' => [$req, 'date_format:Y-m-d',
                Rule::unique('budgets', 'period')->where('district_id', $districtId)->where('poste', $poste)->where('bailleur', $bailleur)->ignore($record?->id)],
            'poste' => ['sometimes', Rule::in(array_keys(Budget::POSTES))],
            'bailleur' => ['sometimes', 'nullable', 'string', 'max:120'],
            'montant_alloue' => [$req, 'numeric', 'min:0'],
        ];
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->merge(['bailleur' => $request->input('bailleur') ?? '']);

        return parent::store($request);
    }

    public function update(Request $request, string $id)
    {
        $request->has('bailleur') && $request->merge(['bailleur' => $request->input('bailleur') ?? '']);

        return parent::update($request, $id);
    }

    public function index(Request $request)
    {
        $page = parent::index($request);

        // Ajoute la situation (dépensé, reste, %, prévision de fin de mois, niveau d'alerte) à chaque budget.
        $tracker = app(\App\Domain\Finance\BudgetTracker::class);
        $page->getCollection()->transform(function (Budget $b) use ($tracker) {
            $b->setAttribute('situation', $tracker->status($b));

            return $b;
        });

        return $page;
    }
}
