<?php

namespace App\Domain\Reports;

use App\Domain\Finance\BudgetTracker;
use App\Domain\Fleet\AlertCenter;
use App\Domain\Fuel\FuelAnalyzer;
use App\Domain\Indicators\IndicatorCatalog;
use App\Domain\Indicators\IndicatorDetails;
use App\Domain\Indicators\IndicatorService;
use App\Models\Budget;
use App\Models\Facture;
use App\Models\FuelPrice;
use App\Models\Immobilisation;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use Carbon\CarbonImmutable;

/** Construit le contenu des 6 rapports prédéfinis : DDKM, flotte, carburant, maintenance, financier, par bailleur. */
class ReportBuilder
{
    public const TYPES = [
        'ddkm' => 'Rapport mensuel DDKM (9 indicateurs)',
        'flotte' => 'Rapport de flotte',
        'carburant' => 'Rapport carburant',
        'maintenance' => 'Rapport maintenance',
        'financier' => 'Rapport financier',
        'bailleur' => 'Rapport par bailleur',
    ];

    /** Types de rapports proposés (le rapport financier suit le module Finance). @return array<string, string> */
    public static function types(): array
    {
        return \App\Support\Modules::enabled('finance') ? self::TYPES : array_diff_key(self::TYPES, ['financier' => true]);
    }

    /** Lignes maximum par tableau dans les rapports (au-delà : mention « … et N autres »). */
    private const MAX_ROWS = 40;

    /**
     * @param  array<int, string>  $ids  districts du périmètre
     */
    public function build(string $type, array $ids, CarbonImmutable $month, string $scopeLabel): ReportDocument
    {
        abort_unless(isset(self::types()[$type]), 422, 'Type de rapport inconnu.');
        $doc = new ReportDocument(self::TYPES[$type], ucfirst($month->translatedFormat('F Y')).' — '.$scopeLabel, $scopeLabel, $month->format('Y-m'));

        return $this->{'build'.ucfirst($type)}($doc, $ids, $month);
    }

    private function fmt(float|int|null $n, int $dec = 0): string
    {
        return number_format((float) $n, $dec, ',', ' ');
    }

    private function pct(float|int|null $n, float|int|null $d): string
    {
        return $d > 0 ? number_format($n / $d * 100, 1, ',', ' ').' %' : '—';
    }

    /** @return array{title: string, svg: string} */
    private function chart(string $title, string $svg): array
    {
        return ['title' => $title, 'svg' => $svg];
    }

    /** Tronque un tableau long et le signale. */
    private function table(string $title, array $headers, array $rows): array
    {
        $extra = count($rows) - self::MAX_ROWS;
        if ($extra > 0) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
            $rows[] = array_merge(["… et {$extra} autre(s) ligne(s)"], array_fill(0, count($headers) - 1, ''));
        }

        return ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    }

    // ------------------------------------------------------------------ DDKM

    private function buildDdkm(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $service = app(IndicatorService::class);
        // Les snapshots manquants du mois sont calculés pour que le rapport soit complet.
        $have = \App\Models\IndicatorSnapshot::whereDate('period', $month->toDateString())->whereIn('district_id', $ids)->distinct()->pluck('district_id')->all();
        foreach (array_diff($ids, $have) as $id) {
            $service->computeForDistrict($id, $month);
        }
        $rows = $service->summaryWithTrend($ids, $month);

        $summary = [];
        foreach (IndicatorCatalog::all() as $key => $meta) {
            $r = $rows[$key];
            $color = IndicatorCatalog::color($key, $r['value']);
            $summary[] = [
                $meta['label'], IndicatorCatalog::format($key, $r['value']),
                $r['previous'] !== null ? IndicatorCatalog::format($key, $r['previous']) : '—',
                $r['delta_pct'] !== null ? sprintf('%+.1f %%', $r['delta_pct']) : '—',
                $meta['target'] !== null ? ($meta['direction'] === 'down' ? '≤ ' : '≥ ').IndicatorCatalog::format($key, $meta['target']) : '—',
                ['success' => 'Conforme', 'warning' => 'À surveiller', 'danger' => 'Hors objectif', 'gray' => '—'][$color],
            ];
        }
        $pctKeys = array_keys(array_filter(IndicatorCatalog::all(), fn ($m) => $m['unit'] === '%'));
        $palette = ['success' => '#22c55e', 'warning' => '#f59e0b', 'danger' => '#ef4444', 'gray' => '#94a3b8'];
        $doc->section('Synthèse des indicateurs DDKM', [], [['title' => 'Les 9 indicateurs', 'headers' => ['Indicateur', 'Valeur', 'Mois précédent', 'Évolution', 'Objectif', 'Situation'], 'rows' => $summary]], [], [
            $this->chart('Indicateurs en pourcentage (vert : conforme · orange : à surveiller · rouge : hors objectif)', ChartSvg::hbars(
                array_map(fn ($k) => IndicatorCatalog::all()[$k]['short'], $pctKeys),
                array_map(fn ($k) => (float) $rows[$k]['value'], $pctKeys), '%',
                array_map(fn ($k) => $palette[IndicatorCatalog::color($k, $rows[$k]['value'])], $pctKeys)
            )),
        ]);

        foreach (IndicatorCatalog::all() as $key => $meta) {
            $detail = IndicatorDetails::blocks($key, $rows[$key], $rows['cout_global']);
            $history = collect($service->history($ids, $key, 12, $month));
            $charts = $history->count() >= 2 ? [$this->chart('Évolution sur 12 mois', ChartSvg::line(
                $history->map(fn ($h) => CarbonImmutable::createFromFormat('Y-m', $h['period'])->translatedFormat('M y'))->all(),
                [['name' => $meta['short'], 'values' => $history->pluck('value')->all()]], $meta['unit'], $meta['target']
            ))] : [];
            $doc->section(
                $meta['label'].' : '.IndicatorCatalog::format($key, $rows[$key]['value']),
                $detail['kpis'],
                array_map(fn ($t) => $this->table($t['title'], $t['headers'], $t['rows']), array_filter($detail['tables'], fn ($t) => count($t['rows']))),
                [$meta['definition']],
                $charts
            );
        }

        // mêmes analyses et mêmes graphiques que le tableau de bord (véhicule × motif)
        $analyse = app(\App\Domain\Fleet\FleetAnalytics::class)->compute($ids, $month->startOfMonth(), $month->endOfMonth());
        $panels = \App\Domain\Fleet\FleetAnalytics::panels($analyse, 12);
        $doc->section('Analyse par véhicule et par motif', [], [], array_values(array_filter(array_map(fn ($p) => $p['svg'] ? null : $p['title'].' : '.lcfirst($p['note'] ?? 'aucune donnée sur la période.'), $panels))),
            array_values(array_filter(array_map(fn ($p) => $p['svg'] ? $this->chart($p['title'].($p['more'] ? ' (12 premiers véhicules)' : ''), $p['svg']) : null, $panels))));

        return $doc->section('Commentaires et recommandations', [], [], Recommendations::for($rows));
    }

    // ------------------------------------------------------------------ flotte

    private function buildFlotte(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $vehicles = Vehicle::with('district:id,name')->whereIn('district_id', $ids)->orderBy('immatriculation')->get();
        $byStatut = $vehicles->countBy('statut');
        $labels = ['disponible' => 'Disponibles', 'en_mission' => 'En mission', 'en_maintenance' => 'Immobilisés', 'hors_service' => 'Hors service'];
        $kpis = [['label' => 'Véhicules', 'value' => (string) $vehicles->count()]];
        foreach ($labels as $k => $l) {
            $kpis[] = ['label' => $l, 'value' => (string) ($byStatut[$k] ?? 0)];
        }
        $kpis[] = ['label' => 'Kilométrage cumulé', 'value' => $this->fmt($vehicles->sum('km_actuel')).' km'];

        $statutColors = ['disponible' => '#22c55e', 'en_mission' => '#2563eb', 'en_maintenance' => '#f59e0b', 'hors_service' => '#ef4444'];
        $topKm = $vehicles->sortByDesc('km_actuel')->take(10)->values();
        $doc->section('Immobilisation des véhicules', $kpis, [$this->table('Liste des véhicules', ['Immatriculation', 'Marque / modèle', 'Type', 'Carburant', 'Statut', 'District', 'Km actuel', 'Prochaine vidange', 'CT', 'Assurance'],
            $vehicles->map(fn (Vehicle $v) => [
                $v->immatriculation, trim("{$v->marque} {$v->modele}"), $v->type_vehicule, FuelPrice::TYPES[$v->type_carburant] ?? $v->type_carburant,
                $labels[$v->statut] ?? $v->statut, $v->district?->name, $this->fmt($v->km_actuel), $v->km_vidange ? $this->fmt($v->km_vidange).' km' : '—',
                $v->date_ct?->format('d/m/Y') ?? '—', $v->date_assurance?->format('d/m/Y') ?? '—',
            ])->all())], [], [
            $this->chart('Répartition par statut', ChartSvg::donut(array_values($labels), array_map(fn ($k) => (int) ($byStatut[$k] ?? 0), array_keys($labels)), 'véhicules', array_values($statutColors))),
            $this->chart('Kilométrage cumulé — 10 véhicules les plus roulants', ChartSvg::hbars($topKm->map(fn (Vehicle $v) => $v->immatriculation.($v->district ? ' · '.$v->district->name : ''))->all(), $topKm->map(fn (Vehicle $v) => (float) $v->km_actuel)->all(), 'km')),
        ]);

        $alerts = AlertCenter::all($ids)->filter(fn ($a) => $a['type'] !== 'budget');

        return $doc->section('Alertes en cours', [], [$this->table('Maintenance et échéances', ['Niveau', 'Alerte'], $alerts->map(fn ($a) => [$a['level']->emoji().' '.$a['level']->label(), $a['message']])->all())]);
    }

    // ------------------------------------------------------------------ carburant

    private function buildCarburant(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $analyzer = app(FuelAnalyzer::class);
        $rows = $analyzer->perVehicle($ids, $month, $month->endOfMonth());
        $prev = $analyzer->perVehicle($ids, $month->subMonth()->startOfMonth(), $month->subMonth()->endOfMonth());
        $cout = $rows->sum('cout');
        $prevCout = $prev->sum('cout');

        $series = $analyzer->monthlySeries($ids, 12);
        $motifs = $analyzer->byMotif($ids, $month, $month->endOfMonth());
        $perVeh = $rows->filter(fn ($r) => $r['reelle'] !== null)->take(10)->values();
        $doc->section('Consommation du mois', [
            ['label' => 'Litres', 'value' => $this->fmt($rows->sum('litres')).' L'],
            ['label' => 'Coût', 'value' => $this->fmt($cout).' FCFA'],
            ['label' => 'Évolution du coût vs mois précédent', 'value' => $prevCout > 0 ? sprintf('%+.1f %%', ($cout - $prevCout) / $prevCout * 100) : '—'],
            ['label' => 'Véhicules en surconsommation', 'value' => (string) $rows->where('surconsommation', true)->count()],
        ], [
            $this->table('Par véhicule', ['Véhicule', 'Km', 'Litres', 'Réelle (L/100)', 'Théorique (L/100)', 'Écart', 'Coût', 'Coût/km', 'Statut'],
                $rows->map(fn ($r) => [$r['vehicle']->immatriculation, $this->fmt($r['km']), $this->fmt($r['litres'], 1), $r['reelle'] ?? '—', $r['theorique'] ?? '—',
                    $r['ecart'] !== null ? sprintf('%+.1f %%', $r['ecart']) : '—', $this->fmt($r['cout']), $r['cout_km'] !== null ? $this->fmt($r['cout_km']) : '—',
                    $r['surconsommation'] ? '⚠ Surconsommation' : ($r['ecart'] !== null ? 'Conforme' : 'Données insuffisantes')])->values()->all()),
            $this->table('Par motif de déplacement', ['Motif', 'Litres', 'Coût'],
                $motifs->map(fn ($v, $k) => [SortieVehicule::MOTIFS[$k] ?? 'Non affecté', $this->fmt($v['litres'], 1), $this->fmt($v['cout'])])->values()->all()),
        ], [], [
            $this->chart('Litres par motif de déplacement', ChartSvg::donut($motifs->keys()->map(fn ($k) => SortieVehicule::MOTIFS[$k] ?? 'Non affecté')->all(), $motifs->pluck('litres')->all(), 'L')),
            $this->chart('Consommation réelle et théorique par véhicule (L/100 km)', ChartSvg::bars(
                $perVeh->map(fn ($r) => $r['vehicle']->immatriculation)->all(),
                [['name' => 'Réelle', 'values' => $perVeh->pluck('reelle')->all(), 'color' => '#2563eb'], ['name' => 'Théorique', 'values' => $perVeh->pluck('theorique')->all(), 'color' => '#94a3b8']], 'L/100'
            )),
        ]);

        return $doc->section('Évolution sur 12 mois', [], [$this->table('Série mensuelle', ['Mois', 'Litres', 'Coût (FCFA)', 'Km', 'Réelle (L/100)', 'Théorique (L/100)'],
            array_map(fn ($s) => [ucfirst($s['label']), $this->fmt($s['litres'], 1), $this->fmt($s['cout']), $this->fmt($s['km']), $s['reelle'] ?? '—', $s['theorique'] ?? '—'], $series))], [], [
            $this->chart('Litres consommés par mois', ChartSvg::bars(array_map(fn ($s) => ucfirst($s['label']), $series), [['name' => 'Litres', 'values' => array_column($series, 'litres')]], 'L')),
            $this->chart('Coût du carburant par mois (FCFA)', ChartSvg::line(array_map(fn ($s) => ucfirst($s['label']), $series), [['name' => 'Coût', 'values' => array_column($series, 'cout'), 'color' => '#0d9488']], 'FCFA')),
            $this->chart('Consommation moyenne : réelle et théorique (L/100 km)', ChartSvg::line(array_map(fn ($s) => ucfirst($s['label']), $series), [['name' => 'Réelle', 'values' => array_column($series, 'reelle'), 'color' => '#2563eb'], ['name' => 'Théorique', 'values' => array_column($series, 'theorique'), 'color' => '#94a3b8']], 'L/100')),
        ]);
    }

    // ------------------------------------------------------------------ maintenance

    private function buildMaintenance(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $vidanges = Vidange::with('vehicle:id,immatriculation')->whereIn('district_id', $ids)->whereDateBetween('date', $from, $to)->orderBy('date')->get();
        $immos = Immobilisation::with('vehicle:id,immatriculation')->whereIn('district_id', $ids)
            ->whereDate('date_debut', '<=', $to)->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $from))->orderBy('date_debut')->get();

        $immoByMotif = $immos->groupBy('motif')->map->count();
        $doc->section('Maintenance du mois', [
            ['label' => 'Vidanges', 'value' => $vidanges->count().' ('.$this->fmt($vidanges->sum('montant')).' FCFA)'],
            ['label' => 'Immobilisations', 'value' => $immos->count().' ('.$this->fmt($immos->sum('montant')).' FCFA)'],
            ['label' => 'En cours', 'value' => (string) $immos->where('statut', '!=', 'terminee')->count()],
            ['label' => 'Durée moyenne', 'value' => $immos->isNotEmpty() ? $this->fmt($immos->avg('duree_jours'), 1).' j' : '—'],
        ], [
            $this->table('Vidanges', ['Date', 'Véhicule', 'Km', 'Type', 'Montant', 'Prestataire', 'Prochaine à'],
                $vidanges->map(fn ($v) => [$v->date->format('d/m/Y'), $v->vehicle?->immatriculation, $this->fmt($v->km), ['simple' => 'Simple', 'complete' => 'Complète', 'revision' => 'Révision'][$v->type] ?? $v->type,
                    $this->fmt($v->montant), $v->prestataire, $v->prochain_km ? $this->fmt($v->prochain_km).' km' : '—'])->all()),
            $this->table('Immobilisations', ['Véhicule', 'Début', 'Fin', 'Durée', 'Motif', 'Coût', 'Statut'],
                $immos->map(fn ($i) => [$i->vehicle?->immatriculation, $i->date_debut->format('d/m/Y'), $i->date_fin?->format('d/m/Y') ?? 'en cours', $i->duree_jours.' j',
                    Immobilisation::MOTIFS[$i->motif] ?? $i->motif, $this->fmt($i->montant), Immobilisation::STATUTS[$i->statut] ?? $i->statut])->all()),
        ], [], [
            $this->chart('Coûts du mois (FCFA)', ChartSvg::bars(['Vidanges', 'Immobilisations'], [['name' => 'Coût', 'values' => [(float) $vidanges->sum('montant'), (float) $immos->sum('montant')]]], 'FCFA')),
            $this->chart('Immobilisations par motif', ChartSvg::donut($immoByMotif->keys()->map(fn ($k) => Immobilisation::MOTIFS[$k] ?? $k)->all(), $immoByMotif->values()->all(), 'immobilisations')),
        ]);

        return $doc->section('Alertes de maintenance', [], [$this->table('À traiter', ['Niveau', 'Alerte'],
            AlertCenter::all($ids)->filter(fn ($a) => $a['type'] !== 'budget')->map(fn ($a) => [$a['level']->emoji().' '.$a['level']->label(), $a['message']])->all())]);
    }

    // ------------------------------------------------------------------ financier

    private function buildFinancier(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $tracker = app(BudgetTracker::class);
        $s = $tracker->monthSummary($ids, $month);

        $postes = collect($s['postes']);
        $doc->section('Budget et dépenses', [
            ['label' => 'Budget alloué', 'value' => $this->fmt($s['alloue']).' FCFA'],
            ['label' => 'Dépenses', 'value' => $this->fmt($s['depense']).' FCFA'],
            ['label' => $s['reste'] >= 0 ? 'Reste' : 'Dépassement', 'value' => $this->fmt(abs($s['reste'])).' FCFA'],
            ['label' => 'Prévision de fin de mois', 'value' => $this->fmt($s['prevision']).' FCFA'],
        ], [$this->table('Par poste', ['Poste', 'Alloué', 'Dépensé', 'Consommé'],
            $postes->map(fn ($p, $k) => [Budget::POSTES[$k], $this->fmt($p['alloue']), $this->fmt($p['depense']), $this->pct($p['depense'], $p['alloue'])])->values()->all())], [], [
            $this->chart('Répartition des dépenses par poste (FCFA)', ChartSvg::donut($postes->keys()->map(fn ($k) => Budget::POSTES[$k])->all(), $postes->pluck('depense')->map(fn ($v) => (float) $v)->all(), 'FCFA')),
            $this->chart('Budget alloué et dépensé par poste (FCFA)', ChartSvg::bars($postes->keys()->map(fn ($k) => Budget::POSTES[$k])->all(), [['name' => 'Alloué', 'values' => $postes->pluck('alloue')->map(fn ($v) => (float) $v)->all(), 'color' => '#94a3b8'], ['name' => 'Dépensé', 'values' => $postes->pluck('depense')->map(fn ($v) => (float) $v)->all(), 'color' => '#2563eb']], 'FCFA')),
        ]);

        $budgets = Budget::with('district:id,name')->whereIn('district_id', $ids)->whereDate('period', $month->toDateString())->orderBy('district_id')->get();
        $doc->section('Détail des budgets', [], [$this->table('Budgets du mois', ['District', 'Poste', 'Bailleur', 'Alloué', 'Dépensé', 'Reste', 'Situation'],
            $budgets->map(function (Budget $b) use ($tracker) {
                $st = $tracker->status($b);

                return [$b->district?->name, Budget::POSTES[$b->poste], $b->bailleur ?: 'Tous', $this->fmt($st['alloue']), $this->fmt($st['depense']), $this->fmt($st['reste']),
                    ['depasse' => 'Dépassé', 'prevision_depassement' => 'Dépassement prévu', 'attention' => 'À surveiller', 'ok' => 'Dans le budget'][$st['niveau']]];
            })->all())]);

        $factures = Facture::whereIn('district_id', $ids)->get();
        $doc->section('Factures', [], [$this->table('Par statut', ['Statut', 'Nombre', 'Montant (FCFA)'],
            collect(Facture::STATUTS)->map(fn ($l, $k) => [$l, $factures->where('statut', $k)->count(), $this->fmt($factures->where('statut', $k)->sum('montant'))])->values()->all())]);

        $trend = [];
        $raw = ['labels' => [], 'alloue' => [], 'depense' => []];
        for ($i = 5; $i >= 0; $i--) {
            $m = $month->subMonths($i);
            $t = $tracker->monthSummary($ids, $m);
            $trend[] = [ucfirst($m->translatedFormat('F Y')), $this->fmt($t['alloue']), $this->fmt($t['depense']), $this->pct($t['depense'], $t['alloue'])];
            $raw['labels'][] = ucfirst($m->translatedFormat('M y'));
            $raw['alloue'][] = (float) $t['alloue'];
            $raw['depense'][] = (float) $t['depense'];
        }

        return $doc->section('Tendance sur 6 mois', [], [['title' => 'Budget et dépenses', 'headers' => ['Mois', 'Alloué', 'Dépensé', 'Consommé'], 'rows' => $trend]], [], [
            $this->chart('Budget alloué et dépenses sur 6 mois (FCFA)', ChartSvg::bars($raw['labels'], [['name' => 'Alloué', 'values' => $raw['alloue'], 'color' => '#94a3b8'], ['name' => 'Dépensé', 'values' => $raw['depense'], 'color' => '#2563eb']], 'FCFA')),
        ]);
    }

    // ------------------------------------------------------------------ bailleur

    private function buildBailleur(ReportDocument $doc, array $ids, CarbonImmutable $month): ReportDocument
    {
        $tracker = app(BudgetTracker::class);
        $rows = $tracker->byBailleur($ids, $month);

        $top = collect($rows)->take(8)->values();
        $doc->section('Dépenses par bailleur', [
            ['label' => 'Bailleurs', 'value' => (string) count($rows)], ['label' => 'Total', 'value' => $this->fmt(collect($rows)->sum('total')).' FCFA'],
        ], [$this->table('Synthèse', ['Bailleur', 'Véhicules', 'Carburant', 'Maintenance', 'Autres frais', 'Total', 'Budget alloué', 'Consommé'],
            array_map(fn ($r) => [$r['bailleur'], $r['vehicules'], $this->fmt($r['carburant']), $this->fmt($r['maintenance']), $this->fmt($r['autres']), $this->fmt($r['total']),
                $r['alloue'] > 0 ? $this->fmt($r['alloue']) : '—', $r['pct'] !== null ? round($r['pct']).' %' : '—'], $rows))], [], [
            $this->chart('Total des dépenses par bailleur (FCFA)', ChartSvg::hbars($top->map(fn ($r) => $r['bailleur'] ?: 'Sans bailleur')->all(), $top->pluck('total')->map(fn ($v) => (float) $v)->all(), 'FCFA')),
            $this->chart('Carburant, maintenance et autres frais par bailleur (FCFA)', ChartSvg::bars($top->map(fn ($r) => $r['bailleur'] ?: 'Sans bailleur')->all(), [
                ['name' => 'Carburant', 'values' => $top->pluck('carburant')->map(fn ($v) => (float) $v)->all(), 'color' => '#2563eb'],
                ['name' => 'Maintenance', 'values' => $top->pluck('maintenance')->map(fn ($v) => (float) $v)->all(), 'color' => '#f59e0b'],
                ['name' => 'Autres frais', 'values' => $top->pluck('autres')->map(fn ($v) => (float) $v)->all(), 'color' => '#8b5cf6'],
            ], 'FCFA')),
        ]);

        $perVehicle = $tracker->spentByVehicle($ids, $month);

        return $doc->section('Détail par véhicule', [], [$this->table('Coûts du mois par véhicule', ['Véhicule', 'Bailleur', 'Carburant', 'Maintenance', 'Autres frais', 'Total'],
            collect($perVehicle)->map(fn ($r, $immat) => [$immat, $r['bailleur'] ?: '—', $this->fmt($r['carburant']), $this->fmt($r['maintenance']), $this->fmt($r['autres']), $this->fmt($r['total'])])->values()->all())]);
    }
}
