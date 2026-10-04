<?php

namespace App\Filament\Pages;

use App\Models\Chronogramme;
use App\Models\LivraisonEspc;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Suivi des livraisons (menu unique) : un circuit se lit comme une ligne d'étapes : statut de livraison de chaque site, distances, actions rapides. */
class SuiviCircuits extends Page implements HasActions
{
    use InteractsWithActions;

    protected string $view = 'filament.pages.suivi-circuits';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Suivi des livraisons';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Suivi des livraisons';

    protected static ?string $slug = 'suivi-circuits';

    /** Jour affiché (Y-m-d). */
    public string $date = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_chronogrammes');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tableau')->label('Vue tableau')->icon('heroicon-o-table-cells')->color('gray')
                ->url(\App\Filament\Resources\Livraisons\LivraisonResource::getUrl('index')),
        ];
    }

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = CarbonImmutable::parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDay()->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) ? $this->date : today()->toDateString();
    }

    private function livraison(array $arguments): LivraisonEspc
    {
        abort_unless(auth()->user()->can('update_chronogrammes'), 403);

        return LivraisonEspc::whereHas('chronogramme', fn ($q) => $q->where('district_id', Filament::getTenant()->getKey()))
            ->findOrFail($arguments['id'] ?? null);
    }

    public function livreAction(): Action
    {
        return Action::make('livre')->label('Livrée')->icon('heroicon-o-check-circle')->color('success')->size('sm')->outlined()
            ->modalHeading(fn (array $arguments) => 'Livraison de '.($this->livraison($arguments)->espc?->nom ?? 'ce site'))
            ->fillForm(fn (array $arguments) => ['date_livraison' => $this->livraison($arguments)->chronogramme->date_prevue->toDateString(), 'lieu_livraison' => 'site'])
            ->schema([
                DatePicker::make('date_livraison')->label('Date de livraison')->required()->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                Select::make('lieu_livraison')->label('Lieu')->options(LivraisonEspc::LIEUX)->required(),
                Textarea::make('commentaire')->label('Commentaire (livraison en transit ou en retard)')->maxLength(500),
            ])
            ->action(function (array $data, array $arguments) {
                $this->livraison($arguments)->update([
                    'statut' => 'livre', 'date_livraison' => $data['date_livraison'], 'lieu_livraison' => $data['lieu_livraison'],
                    'raison_non_livraison' => $data['commentaire'] ?? null,
                ]);
                Notification::make()->title('Livraison enregistrée')->success()->send();
            });
    }

    public function nonLivreAction(): Action
    {
        return Action::make('nonLivre')->label('Non livrée')->icon('heroicon-o-x-circle')->color('danger')->size('sm')->outlined()
            ->modalHeading(fn (array $arguments) => 'Non-livraison de '.($this->livraison($arguments)->espc?->nom ?? 'ce site'))
            ->schema([Textarea::make('raison')->label('Raison de la non-livraison')->required()->maxLength(500)->placeholder('Panne véhicule, route impraticable, report bailleur…')])
            ->action(function (array $data, array $arguments) {
                $this->livraison($arguments)->update(['statut' => 'non_livre', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => $data['raison']]);
                Notification::make()->title('Non-livraison enregistrée')->warning()->send();
            });
    }

    public function reinitAction(): Action
    {
        return Action::make('reinit')->label('Remettre en planifiée')->icon('heroicon-o-arrow-uturn-left')->color('gray')->size('sm')->link()
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $this->livraison($arguments)->update(['statut' => 'planifie', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => null]);
                Notification::make()->title('Site remis en planifiée')->success()->send();
            });
    }

    protected function getViewData(): array
    {
        $plans = Chronogramme::with(['vehicle:id,immatriculation', 'driver:id,nom_complet', 'district:id,name', 'circuit.espc', 'livraisons.espc:id,nom,type', 'livraisons.saisiPar:id,name'])
            ->where('district_id', Filament::getTenant()->getKey())->where('statut', '!=', 'annulee')
            ->whereDate('date_prevue', $this->date)->orderBy('heure_depart')->get()
            ->map(fn (Chronogramme $plan) => $this->describe($plan));

        return [
            'plans' => $plans,
            'day' => CarbonImmutable::parse($this->date),
            'canEdit' => auth()->user()->can('update_chronogrammes'),
        ];
    }

    /** Étapes, statuts et indicateurs d'une sortie planifiée. */
    private function describe(Chronogramme $plan): array
    {
        $distances = $plan->circuit?->espc->mapWithKeys(fn ($e) => [$e->id => $e->pivot->distance_km !== null ? (float) $e->pivot->distance_km : null]) ?? collect();
        $stops = $plan->livraisons->map(fn (LivraisonEspc $l) => [
            'livraison' => $l,
            'etat' => match (true) {
                $l->statut === 'non_livre' => 'non_livre',
                $l->statut === 'livre' && ($l->lieu_livraison === 'transit' || $l->retard_jours > 0) => 'alerte',
                $l->statut === 'livre' => 'livre',
                default => 'avenir',
            },
            'distance' => $distances->get($l->espc_id),
        ]);
        // Le circuit part du district et y revient : distance de retour estimée via le GPS (dernier site → point de départ).
        $lastEspc = $plan->circuit?->espc->last();
        $circuit = $plan->circuit;
        $retour = $circuit && $lastEspc && $lastEspc->hasGps() && $circuit->depart_lat !== null && $circuit->depart_lon !== null
            ? \App\Support\Geo::roadEstimateKm($lastEspc->gps_lat, $lastEspc->gps_lon, $circuit->depart_lat, $circuit->depart_lon) : null;
        $done = $plan->livraisons->where('statut', 'livre');
        $onTime = $done->filter(fn ($l) => $l->delai === 'dans_les_delais' && $l->lieu_livraison !== 'transit')->count();

        // Heures de passage estimées : départ + durée du circuit répartie au prorata des kilomètres (40 km/h à défaut de durée).
        $legs = $stops->map(fn ($s) => $s['distance'] ?? 0)->all();
        $totalKm = array_sum($legs) + ($retour ?? 0);
        $minutes = $plan->circuit?->temps_estime_min ?: ($totalKm > 0 ? $totalKm / 40 * 60 : null);
        $start = $plan->heure_depart ? CarbonImmutable::parse($plan->date_prevue->toDateString().' '.$plan->heure_depart) : null;
        $at = function (float $km) use ($start, $minutes, $totalKm) {
            return $start && $minutes && $totalKm > 0 ? $start->addMinutes((int) round($minutes * $km / $totalKm))->format('H:i') : null;
        };
        $cumul = 0.0;
        $stops = $stops->map(function ($s) use (&$cumul, $at) {
            $cumul += $s['distance'] ?? 0;

            return $s + ['heure' => $at($cumul)];
        });

        return [
            'plan' => $plan,
            'stops' => $stops,
            'total' => $plan->livraisons->count(),
            'done' => $done->count(),
            'onTime' => $done->count() ? round($onTime / $done->count() * 100) : null,
            'failed' => $plan->livraisons->where('statut', 'non_livre')->count(),
            'km' => round($totalKm, 1),
            'depart' => $plan->circuit?->point_depart ?: $plan->district->name,
            'depart_heure' => $start?->format('H:i'),
            'retour_km' => $retour,
            'retour_heure' => $at($totalKm),
            'termine' => $plan->livraisons->isNotEmpty() && $plan->livraisons->every(fn ($l) => $l->statut !== 'planifie'),
        ];
    }
}
