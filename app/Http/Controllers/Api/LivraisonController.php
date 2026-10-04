<?php

namespace App\Http\Controllers\Api;

use App\Models\LivraisonEspc;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Suivi des livraisons par site : liste, mise à jour (livré / non livré + raison) et synthèse. */
class LivraisonController extends ApiController
{
    private function query(Request $request)
    {
        $ids = $request->user()->accessibleDistrictIds();

        return LivraisonEspc::query()->with(['espc:id,nom', 'chronogramme:id,date_prevue,circuit_id,district_id,vehicle_id'])
            ->whereHas('chronogramme', fn ($q) => $q->where('statut', '!=', 'annulee')->when($ids !== null, fn ($q) => $q->whereIn('district_id', $ids)));
    }

    public function index(Request $request)
    {
        $this->requirePermission($request, 'view_chronogrammes');
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'statut' => ['nullable', Rule::in(array_keys(LivraisonEspc::STATUTS))]]);

        return $this->query($request)
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->when($request->query('espc_id'), fn ($q, $s) => $q->where('espc_id', $s))
            ->when($request->query('from') || $request->query('to'), fn ($q) => $q->whereHas('chronogramme', fn ($c) => $c
                ->when($request->query('from'), fn ($c, $d) => $c->whereDate('date_prevue', '>=', $d))
                ->when($request->query('to'), fn ($c, $d) => $c->whereDate('date_prevue', '<=', $d))))
            ->latest('updated_at')->paginate($this->perPage($request));
    }

    public function update(Request $request, string $id)
    {
        $this->requirePermission($request, 'update_chronogrammes');
        $livraison = $this->query($request)->findOrFail($id);
        $data = $request->validate([
            'statut' => ['required', Rule::in(array_keys(LivraisonEspc::STATUTS))],
            'date_livraison' => ['nullable', 'date'],
            'lieu_livraison' => ['nullable', Rule::in(array_keys(LivraisonEspc::LIEUX))],
            'raison_non_livraison' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['statut'] === 'livre') {
            $data['date_livraison'] ??= now()->toDateString();
            $data['lieu_livraison'] ??= 'site';
            $data['raison_non_livraison'] = null;
        } elseif ($data['statut'] === 'non_livre') {
            abort_if(blank($data['raison_non_livraison'] ?? null), 422, 'La raison de la non-livraison est obligatoire.');
            $data['date_livraison'] = null;
            $data['lieu_livraison'] = null;
        } else {
            $data = array_merge($data, ['date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => null]);
        }
        $livraison->update($data);

        return $livraison->fresh(['espc:id,nom']);
    }

    public function summary(Request $request)
    {
        $this->requirePermission($request, 'view_chronogrammes');
        $rows = $this->query($request)
            ->when($request->query('from'), fn ($q, $d) => $q->whereHas('chronogramme', fn ($c) => $c->whereDate('date_prevue', '>=', $d)))
            ->when($request->query('to'), fn ($q, $d) => $q->whereHas('chronogramme', fn ($c) => $c->whereDate('date_prevue', '<=', $d)))
            ->get();

        return [
            'prevues' => $rows->count(),
            'livrees' => $rows->where('statut', 'livre')->count(),
            'non_livrees' => $rows->where('statut', 'non_livre')->count(),
            'planifiees' => $rows->where('statut', 'planifie')->count(),
            'dans_les_delais' => $rows->filter(fn ($l) => $l->delai === 'dans_les_delais')->count(),
            'retard_24h' => $rows->filter(fn ($l) => $l->delai === 'retard_24h')->count(),
            'retard_plus_24h' => $rows->filter(fn ($l) => $l->delai === 'retard_plus_24h')->count(),
            'en_transit' => $rows->where('lieu_livraison', 'transit')->count(),
        ];
    }
}
