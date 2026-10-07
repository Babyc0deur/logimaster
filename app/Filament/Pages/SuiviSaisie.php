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
 * Suivi de la saisie : un tableau districts × 12 mois de l'année.
 * Vert = au moins une sortie de véhicule saisie dans le mois ; rouge = rien de saisi ; gris = mois à venir.
 * On repère ainsi les districts qui ne font pas le suivi de leurs véhicules.
 */
class SuiviSaisie extends Page
{
    protected string $view = 'filament.pages.suivi-saisie';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Suivi de la saisie';

    protected static string|\UnitEnum|null $navigationGroup = 'Données';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Suivi de la saisie par district';

    protected static ?string $slug = 'suivi-saisie';

    public int $annee = 0;

    public string $region = '';

    public bool $manquants = false;

    public string $recherche = '';

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

        // nombre de sorties (hors annulées) par district et par mois
        $counts = [];
        DB::table('sorties_vehicules')->whereNull('deleted_at')->where('statut', '!=', 'annulee')
            ->whereIn('district_id', $districts->pluck('id'))
            ->whereBetween('date_sortie', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('district_id, substr(date_sortie, 6, 2) as mois, count(*) as n')
            ->groupBy('district_id', 'mois')->get()
            ->each(function ($r) use (&$counts) {
                $counts[$r->district_id][(int) $r->mois] = (int) $r->n;
            });

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

        return [
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
        ];
    }
}
