<?php

namespace App\Domain\Chronogramme;

use App\Models\Chronogramme;
use App\Models\Circuit;
use Carbon\CarbonImmutable;

/** Génère le planning d'un mois à partir de la fréquence des circuits actifs d'un district. */
class ChronogrammeGenerator
{
    /**
     * @param  int  $weekday  jour de la semaine (1 = lundi … 7 = dimanche) pour les circuits non quotidiens
     * @return int nombre d'entrées créées (les couples circuit/date déjà planifiés sont ignorés)
     */
    public function generate(string $districtId, CarbonImmutable $month, string $vehicleId, ?string $driverId, int $weekday = 1): int
    {
        $created = 0;

        foreach (Circuit::where('district_id', $districtId)->where('statut', 'actif')->get() as $circuit) {
            foreach ($this->datesFor($circuit->frequence, $month, $weekday) as $date) {
                $exists = Chronogramme::where('circuit_id', $circuit->id)->whereDate('date_prevue', $date)
                    ->where('statut', '!=', 'annulee')->exists();
                if ($exists) {
                    continue;
                }
                Chronogramme::create([
                    'district_id' => $districtId,
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                    'circuit_id' => $circuit->id,
                    'date_prevue' => $date,
                    'motif' => 'distribution',
                    'destination' => $circuit->nom,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return array<int, string> dates Y-m-d */
    public function datesFor(?string $frequence, CarbonImmutable $month, int $weekday): array
    {
        $first = $month->startOfMonth();
        $last = $month->endOfMonth();
        $dates = [];

        switch (strtolower((string) $frequence)) {
            case 'quotidien':
                for ($d = $first; $d <= $last; $d = $d->addDay()) {
                    if ($d->isoWeekday() <= 5) {
                        $dates[] = $d->toDateString();
                    }
                }
                break;
            case 'hebdomadaire':
                for ($d = $first; $d <= $last; $d = $d->addDay()) {
                    if ($d->isoWeekday() === $weekday) {
                        $dates[] = $d->toDateString();
                    }
                }
                break;
            case 'bimensuel':
                $weeks = [];
                for ($d = $first; $d <= $last; $d = $d->addDay()) {
                    if ($d->isoWeekday() === $weekday) {
                        $weeks[] = $d->toDateString();
                    }
                }
                $dates = array_filter([$weeks[0] ?? null, $weeks[2] ?? ($weeks[1] ?? null)]);
                break;
            case 'mensuel':
                for ($d = $first; $d <= $last; $d = $d->addDay()) {
                    if ($d->isoWeekday() === $weekday) {
                        $dates[] = $d->toDateString();
                        break;
                    }
                }
                break;
        }

        return array_values(array_unique($dates));
    }
}
