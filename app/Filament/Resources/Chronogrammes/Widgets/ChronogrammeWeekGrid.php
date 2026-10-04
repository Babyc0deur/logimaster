<?php

namespace App\Filament\Resources\Chronogrammes\Widgets;

use App\Filament\Resources\Chronogrammes\ChronogrammeResource;
use App\Models\Chronogramme;
use App\Models\Vehicle;
use App\Support\DashboardFilters;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Validation\ValidationException;

/**
 * Planning visuel : vue semaine (véhicules × jours) ou vue mois (calendrier), couleurs par motif.
 * Glisser-déposer pour reprogrammer (date, et véhicule en vue semaine) ; clic sur un jour vide pour créer.
 */
class ChronogrammeWeekGrid extends Widget
{
    public const MOTIF_COLORS = [
        'distribution' => '#2563eb', 'redistribution' => '#0d9488', 'enlevement_npsp' => '#d97706',
        'supervision' => '#7c3aed', 'coaching' => '#db2777', 'autre' => '#6b7280',
    ];

    protected string $view = 'filament.widgets.chronogramme-week-grid';

    protected int|string|array $columnSpan = 'full';

    public string $mode = 'week';          // week | month

    public int $weekOffset = 0;

    public int $monthOffset = 0;

    /**
     * Le calendrier s'ouvre sur la fin de la période par défaut (config « logimaster.default_period »), en vue mois ;
     * sans période configurée, sur la semaine en cours.
     */
    public function mount(): void
    {
        $this->goToDefault();
    }

    private function goToDefault(): void
    {
        if (! DashboardFilters::filterUntil()) {
            $this->weekOffset = $this->monthOffset = 0;

            return;
        }
        $anchor = DashboardFilters::defaultUntil();
        $now = CarbonImmutable::now();
        $this->mode = 'month';
        $this->monthOffset = (int) $now->startOfMonth()->diffInMonths($anchor->startOfMonth(), false);
        $this->weekOffset = (int) round($now->startOfWeek()->diffInDays($anchor->startOfWeek(), false) / 7);
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['week', 'month'], true) ? $mode : 'week';
    }

    public function previous(): void
    {
        $this->mode === 'month' ? $this->monthOffset-- : $this->weekOffset--;
    }

    public function next(): void
    {
        $this->mode === 'month' ? $this->monthOffset++ : $this->weekOffset++;
    }

    public function today(): void
    {
        $this->goToDefault();
    }

    /** Reprogrammation par glisser-déposer : nouvelle date (et nouveau véhicule en vue semaine, « none » = sans véhicule). */
    public function movePlan(string $planId, string $date, ?string $vehicleId = null): void
    {
        if (! auth()->user()->can('update_chronogrammes')) {
            Notification::make()->title('Action non autorisée')->danger()->send();

            return;
        }
        $plan = Chronogramme::where('district_id', Filament::getTenant()->getKey())->find($planId);
        if (! $plan) {
            return;
        }
        if ($plan->isLocked()) {
            Notification::make()->title('Planning validé : modification impossible')->body('Demandez la levée de la validation au superviseur.')->danger()->send();

            return;
        }
        if (in_array($plan->statut, ['realisee', 'annulee'], true)) {
            Notification::make()->title('Cette sortie est '.($plan->statut === 'realisee' ? 'déjà réalisée' : 'annulée').' : elle ne peut plus être déplacée.')->warning()->send();

            return;
        }
        if (CarbonImmutable::parse($date)->isBefore(today())) {
            Notification::make()->title('Impossible de planifier dans le passé')->warning()->send();

            return;
        }

        $newVehicle = $vehicleId === null ? $plan->vehicle_id : ($vehicleId === 'none' ? null : $vehicleId);
        if ($newVehicle && Chronogramme::where('vehicle_id', $newVehicle)->whereDate('date_prevue', $date)->where('statut', '!=', 'annulee')->whereKeyNot($plan->getKey())->exists()) {
            Notification::make()->title('Ce véhicule est déjà planifié ce jour-là')->warning()->send();

            return;
        }
        if ($newVehicle && ! Vehicle::where('district_id', $plan->district_id)->whereKey($newVehicle)->exists()) {
            return;
        }

        try {
            $plan->update(['date_prevue' => $date, 'vehicle_id' => $newVehicle]);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }
        Notification::make()->title('Sortie reprogrammée au '.CarbonImmutable::parse($date)->translatedFormat('d F Y'))->success()->send();
    }

    protected function getViewData(): array
    {
        $districtId = Filament::getTenant()?->getKey();
        $month = CarbonImmutable::now()->startOfMonth()->addMonths($this->monthOffset);
        $weekStart = CarbonImmutable::now()->startOfWeek()->addWeeks($this->weekOffset);

        if ($this->mode === 'month') {
            $from = $month->startOfWeek();
            $to = $month->endOfMonth()->endOfWeek();
            $label = ucfirst($month->translatedFormat('F Y'));
        } else {
            $from = $weekStart;
            $to = $weekStart->addDays(6);
            $label = $weekStart->translatedFormat('d M').' → '.$weekStart->addDays(6)->translatedFormat('d M Y');
        }

        $plans = Chronogramme::with(['driver:id,nom_complet', 'circuit:id,nom'])->where('district_id', $districtId)
            ->whereDateBetween('date_prevue', $from, $to)->where('statut', '!=', 'annulee')->orderBy('heure_depart')->get();

        $days = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $days[] = $d;
        }

        return [
            'mode' => $this->mode,
            'label' => $label,
            'days' => collect($days),
            'weeks' => collect($days)->chunk(7),
            'month' => $month,
            'plansByVehicleDay' => $plans->groupBy(fn ($p) => ($p->vehicle_id ?? 'none').'|'.$p->date_prevue->toDateString()),
            'plansByDay' => $plans->groupBy(fn ($p) => $p->date_prevue->toDateString()),
            'vehicles' => Vehicle::where('district_id', $districtId)->orderBy('immatriculation')->get(['id', 'immatriculation'])
                ->push((object) ['id' => 'none', 'immatriculation' => 'Sans véhicule']),
            'colors' => self::MOTIF_COLORS,
            'canEdit' => auth()->user()->can('update_chronogrammes'),
            'createUrl' => ChronogrammeResource::getUrl('create'),
        ];
    }
}
