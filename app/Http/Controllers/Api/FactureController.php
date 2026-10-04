<?php

namespace App\Http\Controllers\Api;

use App\Models\Facture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Factures fournisseurs et workflow : brouillon → soumise → validée/rejetée → payée → archivée. */
class FactureController extends CrudController
{
    protected string $model = Facture::class;

    protected string $permission = 'factures';

    protected array $filters = ['statut', 'categorie', 'vehicle_id', 'fournisseur', 'bailleur'];

    protected array $with = ['vehicle:id,immatriculation', 'createur:id,name'];

    protected string $orderBy = 'date_facture';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';
        $request = request();
        $districtId = $record?->district_id ?? $request->input('district_id');
        $fournisseur = $request->input('fournisseur', $record?->fournisseur);

        return [
            'numero' => [$req, 'string', 'max:60',
                Rule::unique('factures', 'numero')->where('district_id', $districtId)->where('fournisseur', $fournisseur)->ignore($record?->id)],
            'fournisseur' => [$req, 'string', 'max:160'],
            'date_facture' => [$req, 'date', 'before_or_equal:today'],
            'categorie' => [$req, Rule::in(array_keys(Facture::CATEGORIES))],
            'montant' => [$req, 'numeric', 'gt:0'],
            'vehicle_id' => ['nullable', 'uuid', $this->existsInScope($request, 'vehicles')],
            'bailleur' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'create_factures');
        $data = $request->validate($this->rules(null) + ['district_id' => ['required', 'uuid', 'exists:districts,id']]);
        $this->assertDistrictAccess($request, $data['district_id']);

        $facture = Facture::create($data + ['cree_par' => $request->user()->getKey(), 'statut' => 'brouillon']);

        return response()->json($facture->load($this->with), 201);
    }

    public function update(Request $request, string $id)
    {
        abort_unless($this->find($request, $id)->isEditable(), 422, 'Une facture soumise, validée ou payée n\'est plus modifiable.');

        return parent::update($request, $id);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->find($request, $id)->statut === 'brouillon', 422, 'Seul un brouillon peut être supprimé.');

        return parent::destroy($request, $id);
    }

    /** Joint le fichier de la facture (PDF / image). */
    public function upload(Request $request, string $id)
    {
        $this->requirePermission($request, 'update_factures');
        $facture = $this->find($request, $id);
        abort_unless($facture->isEditable(), 422, 'Une facture soumise, validée ou payée n\'est plus modifiable.');
        $request->validate(['fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png']]);

        $facture->update(['fichier_path' => $request->file('fichier')->store("factures/{$facture->district_id}", 'local')]);

        return $facture->fresh();
    }

    public function download(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_factures');
        $facture = $this->find($request, $id);
        abort_unless($facture->fichier_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($facture->fichier_path), 404, 'Aucun fichier joint.');

        return \Illuminate\Support\Facades\Storage::disk('local')->download($facture->fichier_path);
    }

    public function submit(Request $request, string $id)
    {
        $this->requirePermission($request, 'create_factures');

        return $this->find($request, $id)->soumettre($request->user())->fresh();
    }

    public function validateFacture(Request $request, string $id)
    {
        $this->requirePermission($request, 'validate_factures');

        return $this->find($request, $id)->valider($request->user())->fresh();
    }

    public function reject(Request $request, string $id)
    {
        $this->requirePermission($request, 'validate_factures');
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        return $this->find($request, $id)->rejeter($request->user(), $motif)->fresh();
    }

    public function pay(Request $request, string $id)
    {
        $this->requirePermission($request, 'pay_factures');
        $data = $request->validate(['mode' => ['required', Rule::in(array_keys(Facture::MODES_PAIEMENT))], 'reference' => ['nullable', 'string', 'max:80']]);

        return $this->find($request, $id)->payer($request->user(), $data['mode'], $data['reference'] ?? null)->fresh();
    }

    public function archive(Request $request, string $id)
    {
        $this->requirePermission($request, 'pay_factures');

        return $this->find($request, $id)->archiver($request->user())->fresh();
    }
}
