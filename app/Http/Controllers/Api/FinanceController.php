<?php

namespace App\Http\Controllers\Api;

use App\Domain\Finance\BudgetTracker;
use App\Models\Budget;
use App\Models\Facture;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FinanceController extends ApiController
{
    /** Synthèse financière d'un mois : budget vs dépenses par poste, prévision, budgets, bailleurs, alertes, factures en attente. */
    public function summary(Request $request, BudgetTracker $tracker)
    {
        $this->requirePermission($request, 'view_budgets');
        $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $ids = $this->requestedDistrictIds($request) ?? \App\Models\District::pluck('id')->all();
        $month = $request->filled('period')
            ? CarbonImmutable::createFromFormat('Y-m', $request->query('period'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return [
            'period' => $month->format('Y-m'),
            'synthese' => $tracker->monthSummary($ids, $month),
            'budgets' => Budget::with('district:id,name')->whereIn('district_id', $ids)->whereDate('period', $month->toDateString())->get()
                ->map(fn (Budget $b) => $b->toArray() + ['situation' => $tracker->status($b)])->values(),
            'bailleurs' => $tracker->byBailleur($ids, $month),
            'alertes' => $tracker->alerts($ids)->map(fn ($a) => ['level' => $a['level']->value, 'message' => $a['message'], 'district_id' => $a['district_id']])->values(),
            'factures' => collect(Facture::STATUTS)->map(fn ($label, $statut) => [
                'statut' => $statut, 'nombre' => Facture::whereIn('district_id', $ids)->where('statut', $statut)->count(),
                'montant' => (float) Facture::whereIn('district_id', $ids)->where('statut', $statut)->sum('montant'),
            ])->values(),
        ];
    }
}
