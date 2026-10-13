<?php

namespace App\Filament\Pages;

use App\Models\Region;
use App\Support\DashboardFilters;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Cartographie (ex « Suivi de la saisie ») : carte des districts sanitaires et tableau districts × 12 mois de l'année.
 * Vert = au moins une sortie de véhicule saisie dans le mois ; rouge = rien de saisi ; gris = mois à venir.
 * On repère ainsi les districts qui ne font pas le suivi de leurs véhicules.
 */
class SuiviSaisie extends Page
{
    protected string $view = 'filament.pages.suivi-saisie';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Cartographie';

    protected static ?int $navigationSort = 6;   // juste après le chronogramme

    protected static ?string $title = 'Cartographie';

    protected static ?string $slug = 'cartographie';

    public int $annee = 0;

    public string $region = '';

    public bool $manquants = false;

    public string $recherche = '';

    /** Mois de la carte (1 à 12) ; vide = toute l'année. */
    public string $mois = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_indicators');
    }

    public function mount(): void
    {
        $this->annee = (int) DashboardFilters::dashboardFrom()->year;
    }

    public function updatedAnnee(): void
    {
        $this->annee = in_array((int) $this->annee, $this->years(), true) ? (int) $this->annee : (int) now()->year;
    }

    /** Années proposées : de la première sortie saisie à l'année en cours. */
    private function years(): array
    {
        $first = DB::table('sorties_vehicules')->whereNull('deleted_at')->min('date_sortie');
        $from = min($first ? (int) substr($first, 0, 4) : (int) now()->year, (int) DashboardFilters::dashboardFrom()->year);

        return range((int) now()->year, $from);
    }

    public function getViewData(): array
    {
        $year = $this->annee ?: (int) now()->year;
        $districts = DashboardFilters::accessibleDistricts()
            ->with('region:id,name')
            ->when($this->region, fn ($q) => $q->where('region_id', $this->region))
            ->when(trim($this->recherche) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->recherche).'%'))
            ->get(['id', 'name', 'region_id'])
            ->sortBy(fn ($d) => ($d->region?->name ?? '').'|'.$d->name)->values();

        // tous les districts accessibles : le tableau en affiche une partie (filtres), la carte les montre tous
        $everyone = DashboardFilters::accessibleDistricts()->with('region:id,name')->get(['id', 'name', 'region_id', 'latitude', 'longitude']);

        // nombre de sorties (hors annulées) par district et par mois
        $counts = [];
        DB::table('sorties_vehicules')->whereNull('deleted_at')->where('statut', '!=', 'annulee')
            ->whereIn('district_id', $everyone->pluck('id'))
            ->whereBetween('date_sortie', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('district_id, substr(date_sortie, 6, 2) as mois, count(*) as n')
            ->groupBy('district_id', 'mois')->get()
            ->each(function ($r) use (&$counts) {
                $counts[$r->district_id][(int) $r->mois] = (int) $r->n;
            });

        $month = (int) $this->mois;
        $map = $everyone->filter(fn ($d) => $d->latitude !== null)->map(fn ($d) => [
            'n' => $d->name, 'r' => $d->region?->name, 'lat' => round((float) $d->latitude, 4), 'lon' => round((float) $d->longitude, 4),
            'v' => $month >= 1 && $month <= 12 ? ($counts[$d->id][$month] ?? 0) : array_sum($counts[$d->id] ?? []),   // sorties saisies
            'm' => count($counts[$d->id] ?? []),                                                                       // mois renseignés
            'dim' => $this->region !== '' && $d->region_id !== $this->region,
        ])->values()->all();

        $today = CarbonImmutable::now();
        $elapsed = $year < $today->year ? 12 : ($year > $today->year ? 0 : $today->month);   // mois écoulés (le mois en cours compte)
        $rows = $districts->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'region' => $d->region?->name,
            'mois' => $counts[$d->id] ?? [],
            'renseignes' => count($counts[$d->id] ?? []),
        ]);
        if ($this->manquants) {
            $rows = $rows->filter(fn ($r) => $r['renseignes'] < $elapsed)->values();
        }
        $perMonth = [];
        foreach (range(1, 12) as $m) {
            $perMonth[$m] = $rows->filter(fn ($r) => isset($r['mois'][$m]))->count();
        }

        // taux de remplissage : un district est « renseigné » un mois dès qu'une sortie non annulée y est saisie
        $taux = fn (int $ok, int $on) => $on > 0 ? (int) round(100 * $ok / $on) : 0;
        $tauxAnnee = $taux($districts->sum(fn ($d) => count($counts[$d->id] ?? [])), $districts->count() * $elapsed);
        $mapOk = collect($map)->where('v', '>', 0)->count();

        return [
            'tauxAnnee' => $tauxAnnee,
            'tauxCarte' => $taux($mapOk, count($map)),
            'elapsedMap' => $elapsed,
            'rows' => $rows,
            'year' => $year,
            'years' => $this->years(),
            'elapsed' => $elapsed,
            'perMonth' => $perMonth,
            'total' => $districts->count(),
            'complets' => $districts->filter(fn ($d) => count($counts[$d->id] ?? []) >= $elapsed && $elapsed > 0)->count(),
            'aucun' => $districts->filter(fn ($d) => empty($counts[$d->id]))->count(),
            'regions' => Region::whereHas('districts', fn ($q) => $q->whereIn('id', DashboardFilters::accessibleDistricts()->select('id')))->orderBy('name')->pluck('name', 'id'),
            'months' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => CarbonImmutable::create($year, $m, 1)])->all(),
            'map' => $map,
            'mapOk' => $mapOk,
            'mapMissing' => $everyone->count() - count($map),
        ];
    }
}
