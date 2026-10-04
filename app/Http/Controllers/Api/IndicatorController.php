<?php

namespace App\Http\Controllers\Api;

use App\Domain\Indicators\IndicatorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class IndicatorController extends ApiController
{
    public function __construct(private IndicatorService $service) {}

    /** Lit uniquement les snapshots pré-calculés (jamais de calcul à la volée). */
    public function summary(Request $request)
    {
        $this->requirePermission($request, 'view_indicators');
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'scope' => ['nullable', 'in:national,region,district'],
        ]);

        $month = $request->filled('period')
            ? CarbonImmutable::createFromFormat('Y-m', $request->query('period'))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $ids = $this->requestedDistrictIds($request);

        return [
            'period' => $month->format('Y-m'),
            'districts' => $ids === null ? 'all' : $ids,
            'indicators' => $this->service->summary($ids, $month),
        ];
    }

    public function history(Request $request, string $key)
    {
        $this->requirePermission($request, 'view_indicators');
        abort_unless(in_array($key, $this->service->keys(), true), 404, 'Indicateur inconnu.');
        $request->validate(['months' => ['nullable', 'integer', 'between:1,60']]);
        $months = (int) $request->query('months', 12);

        return [
            'key' => $key,
            'history' => $this->service->history($this->requestedDistrictIds($request), $key, $months),
        ];
    }
}
