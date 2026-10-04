<?php

namespace App\Domain\Indicators;

use App\Models\Immobilisation;
use Carbon\CarbonImmutable;

/** Jours d'indisponibilité par véhicule sur une période (immobilisations qui se chevauchent comptées une seule fois par jour). */
class ImmobilisationDays
{
    /**
     * @return array{per_vehicle: array<string, int>, per_motif: array<string, int>}
     */
    public static function compute(string $districtId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        $perVehicle = [];
        $perMotif = [];

        $rows = Immobilisation::where('district_id', $districtId)
            ->whereDate('date_debut', '<=', $end)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $start))
            ->get();

        $days = [];
        foreach ($rows as $row) {
            $from = $row->date_debut->toImmutable()->startOfDay()->max($start);
            $to = ($row->date_fin?->toImmutable()->startOfDay() ?? $end)->min($end);
            for ($d = $from; $d <= $to; $d = $d->addDay()) {
                $key = $row->vehicle_id.'|'.$d->toDateString();
                if (! isset($days[$key])) {
                    $days[$key] = true;
                    $perVehicle[$row->vehicle_id] = ($perVehicle[$row->vehicle_id] ?? 0) + 1;
                    $perMotif[$row->motif] = ($perMotif[$row->motif] ?? 0) + 1;
                }
            }
        }

        return ['per_vehicle' => $perVehicle, 'per_motif' => $perMotif];
    }
}
