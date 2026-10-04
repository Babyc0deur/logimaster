<?php

namespace App\Domain\Import\Workbook;

use App\Domain\Import\ImportException;
use App\Domain\Import\SpreadsheetImporter;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Domain\Import\Harmonizer;
use App\Models\District;
use App\Models\Driver;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\Immobilisation;
use App\Models\Personnel;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Importe le classeur Excel « Logimaster » (.xlsx / .xlsm) d'un district : sites, véhicules, chronogramme,
 * sorties (onglet CIRCUITS), carburant, autres frais, vidanges et immobilisations.
 * Les formules et onglets de calcul (DASHBOARD, CONSOLIDATION…) sont ignorés : l'application recalcule.
 * Réimportable sans doublon : chaque ligne est retrouvée par sa clé métier.
 */
class WorkbookImporter
{
    private SpreadsheetImporter $values;

    /** @var array<string, Vehicle> */
    private array $vehicles = [];

    /** @var array<string, Driver> */
    private array $drivers = [];

    /** @var array<string, Circuit> */
    private array $circuits = [];

    /** @var array<string, Espc> */
    private array $espcs = [];

    /** @var array<string, Personnel> */
    private array $personnels = [];

    /** @var array<string, true> véhicules touchés (recalcul du kilométrage / de l'échéance de vidange) */
    private array $touched = [];

    private District $district;

    /** Mode tolérant (chargement en masse) : une date ou un nombre illisible dans une colonne facultative devient vide. */
    private bool $lenient = false;

    /** Onglet CHRONOGRAMME : ne crée que les circuits et l'ordre de leurs sites, pas les sorties planifiées. */
    private bool $definitionsOnly = false;

    /** @var array{0: ?string, 1: ?string}|null bornes (Y-m-d) : seules les lignes d'activité datées dans cet intervalle sont importées */
    private ?array $period = null;

    /** @var array<int, string> valeurs illisibles ignorées en mode tolérant */
    public array $warnings = [];

    private const PLACEHOLDERS = ['na', 'n/a', 'nd', 'n/d', 'neant', 'aucun', 'aucune', 'non disponible', 'non renseigne', 'nonrenseigne', 'inconnu', '-', '--', '/', '?', 'x'];

    /** Noms d'onglets équivalents selon la version du classeur (slug => nom canonique). */
    private const SHEET_ALIASES = ['listedesespc' => 'Liste des Sites'];

    /** Limite l'import de l'activité (sorties, carburant, frais, immobilisations, chronogramme) à une période. */
    public function forPeriod(?string $from, ?string $until): static
    {
        $this->period = $from || $until ? [$from, $until] : null;

        return $this;
    }

    /** Active (ou non) le mode « circuits seulement » pour les prochains imports. */
    public function circuitsOnly(bool $enabled = true): static
    {
        $this->definitionsOnly = $enabled;

        return $this;
    }

    public function __construct()
    {
        $this->values = new SpreadsheetImporter;
    }

    /** @param  array<int, string>|null  $only  limite l'import à ces onglets (ex. ['Liste des Sites', 'VEHICULES']) */
    public function import(string $path, string $districtId, bool $skipInvalid = false, ?array $only = null, bool $lenient = false): WorkbookReport
    {
        $this->lenient = $lenient;
        $this->warnings = [];
        $this->district = District::findOrFail($districtId);
        $report = new WorkbookReport;
        $wanted = $only === null ? null : array_map(fn ($n) => $this->values->slug($n), $only);
        $data = $this->readSheets($path, $wanted);
        $this->loadReferences();

        DB::beginTransaction();
        try {
            foreach (WorkbookSpec::sheets() as $name => $spec) {
                if ($wanted !== null && ! in_array($this->values->slug($name), $wanted, true)) {
                    continue;
                }
                $rows = $data[$this->values->slug($name)] ?? null;
                if ($rows === null) {
                    $report->missing[] = $name;

                    continue;
                }
                $sheet = $report->sheet($name);
                try {
                    $records = $this->records($rows, $spec);
                } catch (ImportException $e) {
                    $sheet->addError(1, $e->getMessage());

                    continue;
                }
                $method = 'sheet'.str_replace(' ', '', ucwords(strtolower($name)));

                foreach ($this->{$method}($records) as $line => $handler) {
                    try {
                        $result = DB::transaction($handler);
                        $result === 'created' ? $sheet->created++ : ($result === 'updated' ? $sheet->updated++ : null);
                    } catch (ImportException $e) {
                        $sheet->addError($line, $e->getMessage());
                    }
                }
            }
            $this->finalize();

            if ($report->hasErrors() && ! $skipInvalid) {
                DB::rollBack();
                foreach ($report->sheets as $sheet) {
                    $sheet->created = $sheet->updated = 0;
                }
            } else {
                DB::commit();
                $report->committed = true;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    // ------------------------------------------------------------------ lecture

    /** @return array<string, array<int, array<int, mixed>>> slug de l'onglet => [numéro de ligne => cellules] */
    private function readSheets(string $path, ?array $wanted = null): array
    {
        // Lignes vides conservées : les numéros de ligne des messages d'erreur sont ceux affichés par Excel.
        $options = new \OpenSpout\Reader\XLSX\Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $reader->open($path);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $slug = $this->values->slug($sheet->getName());
            $slug = $this->values->slug(self::SHEET_ALIASES[$slug] ?? $slug); // versions du classeur : « Liste des ESPC » = « Liste des Sites »
            if (! in_array($slug, array_map(fn ($n) => $this->values->slug($n), array_keys(WorkbookSpec::sheets())), true)) {
                continue; // onglets de calcul / listes : inutiles
            }
            if ($wanted !== null && ! in_array($slug, $wanted, true)) {
                continue;
            }
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $out[$slug][$line] = array_map(fn ($c) => is_string($c) ? trim($c) : $c, $row->toArray());
            }
        }
        $reader->close();

        return $out;
    }

    /**
     * Repère la ligne d'en-têtes puis transforme chaque ligne de données en tableau champ => valeur.
     *
     * @return array<int, array<string, mixed>>
     */
    private function records(array $rows, array $spec): array
    {
        $keyNeedles = (array) $spec['fields'][$spec['key']];
        $headerLine = null;
        foreach (array_slice($rows, 0, 12, true) as $line => $cells) {
            foreach ($cells as $cell) {
                if (is_string($cell) && $this->containsAny($this->values->slug($cell), $keyNeedles)) {
                    $headerLine = $line;
                    break 2;
                }
            }
        }
        if ($headerLine === null) {
            throw new ImportException('Onglet reconnu mais en-têtes introuvables (colonne « '.implode(' / ', $keyNeedles).' »). Utilisez le modèle Logimaster.');
        }

        $slugs = array_map(fn ($c) => is_string($c) ? $this->values->slug($c) : '', $rows[$headerLine]);
        $map = [];
        foreach ($spec['fields'] as $field => $needles) {
            foreach ((array) $needles as $needle) {
                $index = array_search($needle, $slugs, true);
                if ($index === false) {
                    foreach ($slugs as $i => $slug) {
                        if ($slug !== '' && str_contains($slug, $needle)) {
                            $index = $i;
                            break;
                        }
                    }
                }
                if ($index !== false) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        $records = [];
        foreach ($rows as $line => $cells) {
            if ($line <= $headerLine) {
                continue;
            }
            $record = [];
            foreach ($map as $field => $index) {
                $v = $cells[$index] ?? null;
                $record[$field] = ($v === '' || $v === null) ? null : $v;
            }
            if (($record[$spec['key']] ?? null) === null || $this->isTotalRow($record)) {
                continue;
            }
            if (! $this->inPeriod($record)) {
                continue;
            }
            $records[$line] = $record;
        }

        return $records;
    }

    private function isTotalRow(array $record): bool
    {
        return in_array(mb_strtolower((string) ($record['immat'] ?? $record['nom'] ?? '')), ['total', 'totaux'], true);
    }

    private function containsAny(string $slug, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($slug, $n)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ onglets (retournent line => closure)

    /** @return array<int, \Closure> */
    private function sheetListeDesSites(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                if (Harmonizer::isPlaceholderSite((string) $r['nom'])) {
                    return null;
                }
                $existed = isset($this->espcs[$this->values->slug((string) $r['nom'])]);
                $this->espc((string) $r['nom']);

                return $existed ? 'updated' : 'created';
            };
        }

        return $out;
    }

    private function sheetVehicules(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $immat = $this->immat($r['immat']);
                if (! empty($r['district'])) {
                    $named = District::all()->first(fn ($d) => $this->values->slug($d->name) === $this->values->slug((string) $r['district']));
                    if ($named && $named->id !== $this->district->id) {
                        throw new ImportException("Véhicule déclaré pour le district « {$named->name} » (import dans « {$this->district->name} »).");
                    }
                }
                $existing = Vehicle::withTrashed()->where('immatriculation', $immat)->first();
                if ($existing && $existing->district_id !== $this->district->id) {
                    throw new ImportException("L'immatriculation {$immat} appartient à un autre district.");
                }

                $carburant = $this->carburant($r['carburant'] ?? null);
                [$marque, $modele] = Harmonizer::vehicle($r['marque'] ?? null, $r['modele'] ?? null);
                $data = $this->filled([
                    'date_reception' => $this->date($r['date_reception'] ?? null, 'Date de réception'),
                    'bailleur' => Harmonizer::funder($r['bailleur'] ?? null),
                    'marque' => $marque,
                    'modele' => $modele,
                    'vignette_annee' => $this->year($r['vignette'] ?? null, 'Vignette'),
                    'annee_circulation' => $this->yearOfDateOrYear($r['mise_en_circulation'] ?? null, 'Date de mise en circulation'),
                    'date_assurance' => $this->date($r['assurance'] ?? null, 'Validité assurance'),
                    'appartenance' => $this->appartenance($r['appartenance'] ?? null),
                    'type_carburant' => $carburant,
                    'prix_carburant' => $carburant ? FuelPrice::current($carburant) : null,
                    'date_dernier_ct' => $this->date($r['dernier_ct'] ?? null, 'Dernier contrôle technique'),
                    'date_ct' => $this->date($r['prochain_ct'] ?? null, 'Prochain contrôle technique'),
                    'km_vidange' => $this->int($r['vidange_km'] ?? null, 'Vidange au Km'),
                    'poids_vide' => $this->int($r['poids'] ?? null, 'Poids à vide'),
                    'commentaire' => $r['commentaire'] ?? null,
                ]);

                if ($existing) {
                    $existing->trashed() && $existing->restore();
                    $existing->update($data + ['version' => $existing->version + 1]);
                    $this->vehicles[$this->vkey($immat)] = $existing;

                    return 'updated';
                }
                $this->vehicles[$this->vkey($immat)] = Vehicle::create($data + ['district_id' => $this->district->id, 'immatriculation' => $immat]);

                return 'created';
            };
        }

        return $out;
    }

    private function sheetChronogramme(array $records): array
    {
        // Une ligne du classeur = un site visité ; on regroupe par circuit + date pour reconstituer la sortie planifiée.
        $groups = [];
        $errors = [];
        foreach ($records as $line => $r) {
            // Ligne résiduelle du modèle (nom de circuit sans date ni site) : rien à planifier.
            if (empty($r['date']) && empty($r['site']) && empty($r['mois'])) {
                continue;
            }
            if (empty($r['circuit'])) {
                $errors[$line] = fn () => throw new ImportException('Nom du circuit obligatoire.');

                continue;
            }
            $date = null;
            try {
                $date = $this->date($r['date'] ?? null, 'Date de déplacement planifiée') ?? $this->date($r['mois'] ?? null, 'Mois');
            } catch (ImportException $e) {
                if (! $this->definitionsOnly) {
                    $errors[$line] = fn () => throw $e;

                    continue;
                }
            }
            if ($this->definitionsOnly) {
                $date = '';
            }
            if ($date === null) {
                $errors[$line] = fn () => throw new ImportException('Date de déplacement planifiée obligatoire.');

                continue;
            }
            $key = $this->values->slug((string) $r['circuit']).'|'.$date;
            if ($this->definitionsOnly && empty($r['site'])) {
                continue;
            }
            $groups[$key]['line'] ??= $line;
            $groups[$key]['circuit'] ??= (string) $r['circuit'];
            $groups[$key]['date'] ??= $date;
            $groups[$key]['motif'] ??= $this->motif($r['motif'] ?? null);
            $groups[$key]['commentaire'] ??= $r['commentaire'] ?? null;
            foreach (preg_split('/[;\n]+/', (string) ($r['site'] ?? '')) as $site) {
                if (trim($site) !== '' && ! Harmonizer::isPlaceholderSite($site)) {
                    $groups[$key]['sites'][] = trim($site);
                }
            }
        }

        $out = $errors;
        foreach ($groups as $g) {
            $out[$g['line']] = function () use ($g) {
                if ($this->definitionsOnly && ! Harmonizer::isRoute($g['circuit'], collect($g['sites'] ?? [])->unique(fn ($x) => $this->values->slug($x))->count())) {
                    return null; // motif ou destination, pas un parcours
                }
                $circuitExisted = isset($this->circuits[$this->values->slug($g['circuit'])]);
                $circuit = $this->circuit($g['circuit']);
                $sites = collect($g['sites'] ?? [])->unique(fn ($s) => $this->values->slug($s))->values();

                // Les sites du circuit sont la réunion ordonnée de ceux déjà connus et des nouveaux.
                $known = $circuit->espc()->pluck('espc.id')->all();
                foreach ($sites as $site) {
                    $espc = $this->espc($site);
                    if (! in_array($espc->id, $known, true)) {
                        $circuit->espc()->attach($espc->id, ['ordre' => count($known) + 1]);
                        $known[] = $espc->id;
                    }
                }

                if ($this->definitionsOnly) {
                    return $circuitExisted ? 'updated' : 'created';
                }

                $plan = Chronogramme::where('district_id', $this->district->id)->where('circuit_id', $circuit->id)->whereDate('date_prevue', $g['date'])->first();
                $data = $this->filled(['motif' => $g['motif'], 'commentaires' => $g['commentaire'], 'destination' => $circuit->nom]);
                $result = $plan ? 'updated' : 'created';
                $plan ? $plan->update($data) : $plan = Chronogramme::create($data + [
                    'district_id' => $this->district->id, 'circuit_id' => $circuit->id, 'date_prevue' => $g['date'], 'statut' => 'planifiee',
                ]);
                if ($sites->isNotEmpty()) {
                    $plan->espc()->sync($sites->mapWithKeys(fn ($s, $i) => [$this->espc($s)->id => ['ordre' => $i + 1]])->all());
                }

                return $result;
            };
        }
        ksort($out);

        return $out;
    }

    private function sheetCircuits(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $vehicle = $this->vehicle($r['immat']);
                $date = $this->date($r['date'] ?? null, 'Date') ?? throw new ImportException('Date obligatoire.');
                $kmDepart = $this->int($r['km_depart'] ?? null, 'Kilométrage au départ') ?? throw new ImportException('Kilométrage au départ obligatoire.');
                $kmArrivee = $this->int($r['km_arrivee'] ?? null, "Kilométrage d'arrivée");
                if ($kmArrivee !== null && $kmArrivee < $kmDepart) {
                    throw new ImportException("Kilométrage d'arrivée ({$kmArrivee}) inférieur au kilométrage de départ ({$kmDepart}).");
                }
                $circuit = ! empty($r['circuit']) ? $this->circuit((string) $r['circuit']) : null;
                $motif = $this->motif($r['motif'] ?? null);

                // Circuit respecté = une sortie était planifiée ce jour-là sur ce circuit.
                $plan = $circuit ? Chronogramme::where('district_id', $this->district->id)->where('circuit_id', $circuit->id)
                    ->whereDate('date_prevue', $date)->where('statut', '!=', 'annulee')->first() : null;
                $respecte = $circuit ? ($plan !== null ? true : ($motif === 'distribution' ? false : null)) : null;

                $sortie = SortieVehicule::where('district_id', $this->district->id)->where('vehicle_id', $vehicle->id)
                    ->whereDate('date_sortie', $date)->where('km_depart', $kmDepart)->first();
                $data = $this->filled([
                    'driver_id' => ! empty($r['chauffeur']) ? $this->driver((string) $r['chauffeur'])->id : null,
                    'chef_mission_id' => ! empty($r['chef']) ? $this->personnel((string) $r['chef'], 'chef_mission')->id : null,
                    'circuit_id' => $circuit?->id, 'km_arrivee' => $kmArrivee, 'motif' => $motif,
                    'point_depart' => $r['depart'] ?? null, 'point_arrivee' => $r['arrivee'] ?? null, 'commentaires' => $r['commentaire'] ?? null,
                ]) + ['statut' => $kmArrivee !== null ? 'terminee' : 'en_cours', 'circuit_respecte' => $respecte];

                $result = $sortie ? 'updated' : 'created';
                SortieVehicule::withoutEvents(function () use (&$sortie, $data, $vehicle, $date, $kmDepart) {
                    $sortie
                        ? $sortie->update($data)
                        : $sortie = SortieVehicule::create($data + [
                            'owner_type' => 'district', 'owner_id' => $this->district->id, 'district_id' => $this->district->id,
                            'vehicle_id' => $vehicle->id, 'date_sortie' => $date, 'km_depart' => $kmDepart,
                        ]);
                });

                $passagers = collect([$r['p1'] ?? null, $r['p2'] ?? null, $r['p3'] ?? null])->filter()
                    ->map(fn ($n) => $this->personnel((string) $n, 'passager')->id)->unique()->values();
                if ($passagers->isNotEmpty()) {
                    $sortie->passagers()->sync($passagers);
                }
                if ($plan && (! $plan->sortie_id || $plan->sortie_id === $sortie->id)) {
                    $plan->update(['statut' => 'realisee', 'sortie_id' => $sortie->id, 'vehicle_id' => $plan->vehicle_id ?? $vehicle->id, 'driver_id' => $plan->driver_id ?? $sortie->driver_id]);
                    $kmArrivee !== null && $plan->marquerSitesLivres($date);
                }
                $this->touched[$vehicle->id] = true;

                return $result;
            };
        }

        return $out;
    }

    private function sheetCarburant(array $records): array
    {
        // Ordre chronologique : la détection d'anomalies compare chaque plein au précédent.
        uasort($records, fn ($a, $b) => strcmp((string) $this->safeDate($a['date'] ?? null), (string) $this->safeDate($b['date'] ?? null)));

        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $vehicle = $this->vehicle($r['immat']);
                $date = $this->date($r['date'] ?? null, 'Date de transaction') ?? throw new ImportException('Date de transaction obligatoire.');
                $litres = $this->decimal($r['litres'] ?? null, 'Total litres') ?? throw new ImportException('Total litres ravitaillés obligatoire.');
                $pu = $this->decimal($r['pu'] ?? null, 'Prix unitaire')
                    ?? FuelPrice::current($this->carburant($r['carburant'] ?? null) ?? $vehicle->type_carburant, $date)
                    ?? throw new ImportException('Prix unitaire obligatoire (aucun prix carburant défini).');
                $km = $this->int($r['km'] ?? null, 'Relevé kilométrage');

                $sortie = SortieVehicule::where('district_id', $this->district->id)->where('vehicle_id', $vehicle->id)->whereDate('date_sortie', $date)->first();
                $existing = Ravitaillement::where('district_id', $this->district->id)->where('vehicle_id', $vehicle->id)
                    ->whereDate('date_ravitaillement', $date)->where('litres', $litres)->where('km_compteur', $km)->first();
                $data = $this->filled([
                    'driver_id' => ! empty($r['chauffeur']) ? $this->driver((string) $r['chauffeur'])->id : null,
                    'sortie_id' => $sortie?->id, 'prix_unitaire' => $pu, 'km_compteur' => $km, 'numero_facture' => $r['facture'] ?? null,
                    'motif' => ! empty($r['motif']) ? $this->motif($r['motif']) : null,
                ]);

                $existing
                    ? $existing->update($data)
                    : Ravitaillement::create($data + ['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'litres' => $litres, 'date_ravitaillement' => $date, 'prix_unitaire' => $pu]);
                $this->touched[$vehicle->id] = true;

                return $existing ? 'updated' : 'created';
            };
        }

        return $out;
    }

    private function sheetAutresFrais(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $date = $this->date($r['date'] ?? null, 'Date') ?? throw new ImportException('Date obligatoire.');
                $montant = $this->decimal($r['montant'] ?? null, 'Montant total') ?? throw new ImportException('Montant total obligatoire.');
                $vehicle = ! empty($r['immat']) ? $this->vehicle($r['immat']) : null;
                $type = $this->expenseType($r['type'] ?? null);
                $sortie = $vehicle ? SortieVehicule::where('district_id', $this->district->id)->where('vehicle_id', $vehicle->id)->whereDate('date_sortie', $date)->first() : null;

                $existing = Expense::where('district_id', $this->district->id)->whereDate('date_depense', $date)->where('vehicle_id', $vehicle?->id)
                    ->where('type', $type)->where('montant', $montant)->where('beneficiaire', Harmonizer::person($r['beneficiaire'] ?? null))->first();
                $data = $this->filled(['sortie_id' => $sortie?->id, 'motif' => ! empty($r['motif']) ? $this->motif($r['motif']) : null, 'commentaire' => $r['commentaire'] ?? null]);
                $existing
                    ? $existing->update($data)
                    : Expense::create($data + ['district_id' => $this->district->id, 'vehicle_id' => $vehicle?->id, 'date_depense' => $date, 'type' => $type, 'montant' => $montant, 'beneficiaire' => Harmonizer::person($r['beneficiaire'] ?? null)]);

                return $existing ? 'updated' : 'created';
            };
        }

        return $out;
    }

    private function sheetVidanges(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $vehicle = $this->vehicle($r['immat']);
                $date = $this->date($r['date'] ?? null, 'Date de la dernière vidange') ?? throw new ImportException('Date de la dernière vidange obligatoire.');
                $km = $this->int($r['km'] ?? null, 'Kilométrage à la dernière vidange') ?? throw new ImportException('Kilométrage à la dernière vidange obligatoire.');
                $existing = Vidange::where('vehicle_id', $vehicle->id)->whereDate('date', $date)->where('km', $km)->first();
                $data = $this->filled([
                    'montant' => $this->decimal($r['montant'] ?? null, 'Montant'), 'numero_facture' => $r['facture'] ?? null,
                    'prochain_km' => $this->int($r['prochain_km'] ?? null, 'Prochaine vidange'), 'observations' => $r['observations'] ?? null,
                ]);
                Vidange::withoutEvents(fn () => $existing
                    ? $existing->update($data)
                    : Vidange::create($data + ['vehicle_id' => $vehicle->id, 'district_id' => $this->district->id, 'date' => $date, 'km' => $km, 'type' => 'simple']));
                $this->touched[$vehicle->id] = true;

                return $existing ? 'updated' : 'created';
            };
        }

        return $out;
    }

    private function sheetImmobilisation(array $records): array
    {
        $out = [];
        foreach ($records as $line => $r) {
            $out[$line] = function () use ($r) {
                $vehicle = $this->vehicle($r['immat']);
                $debut = $this->date($r['debut'] ?? null, "Date d'indisponibilité") ?? $this->date($r['mois'] ?? null, 'Mois')
                    ?? throw new ImportException("Date d'indisponibilité (ou mois) obligatoire.");
                $fin = $this->date($r['fin'] ?? null, 'Date de disponibilité');
                if ($fin && $fin < $debut) {
                    throw new ImportException('Date de disponibilité antérieure à la date d\'indisponibilité.');
                }
                $motif = $this->immobilisationMotif($r['motif'] ?? null);

                $existing = Immobilisation::where('vehicle_id', $vehicle->id)->whereDate('date_debut', $debut)->where('motif', $motif)->first();
                $data = $this->filled([
                    'date_fin' => $fin, 'montant' => $this->decimal($r['montant'] ?? null, 'Montant'), 'prestataire' => Harmonizer::person($r['prestataire'] ?? null),
                    'description' => $r['commentaire'] ?? null,
                ]) + ['statut' => $fin ? 'terminee' : 'en_cours'];
                $existing
                    ? $existing->update($data)
                    : Immobilisation::create($data + ['district_id' => $this->district->id, 'vehicle_id' => $vehicle->id, 'date_debut' => $debut, 'motif' => $motif]);

                return $existing ? 'updated' : 'created';
            };
        }

        return $out;
    }

    /** Après import : kilométrage actuel et échéance de vidange cohérents avec l'historique (jamais en arrière). */
    private function finalize(): void
    {
        foreach (array_keys($this->touched) as $id) {
            $vehicle = Vehicle::find($id);
            if (! $vehicle) {
                continue;
            }
            $maxKm = max((int) SortieVehicule::where('vehicle_id', $id)->max('km_arrivee'), (int) Ravitaillement::where('vehicle_id', $id)->max('km_compteur'), (int) Vidange::where('vehicle_id', $id)->max('km'));
            $updates = [];
            if ($maxKm > $vehicle->km_actuel) {
                $updates['km_actuel'] = $maxKm;
            }
            $lastOil = Vidange::where('vehicle_id', $id)->whereNotNull('prochain_km')->orderByDesc('date')->orderByDesc('km')->first();
            if ($lastOil && $lastOil->prochain_km > ($vehicle->km_vidange ?? 0)) {
                $updates['km_vidange'] = $lastOil->prochain_km;
            }
            $updates && $vehicle->update($updates);
        }
    }

    // ------------------------------------------------------------------ références (cache par district)

    private function loadReferences(): void
    {
        $id = $this->district->id;
        $this->vehicles = $this->drivers = $this->circuits = $this->espcs = $this->personnels = $this->touched = [];
        foreach (Vehicle::where('district_id', $id)->get() as $v) {
            $this->vehicles[$this->vkey($v->immatriculation)] = $v;
        }
        foreach (Driver::where('district_id', $id)->get() as $d) {
            $this->drivers[$this->values->slug($d->nom_complet)] = $d;
        }
        foreach (Circuit::where('district_id', $id)->get() as $c) {
            $this->circuits[$this->values->slug($c->nom)] = $c;
        }
        foreach (Espc::where('district_id', $id)->get() as $e) {
            $this->espcs[$this->values->slug($e->nom)] = $e;
        }
        foreach (Personnel::where('district_id', $id)->get() as $p) {
            $this->personnels[$p->fonction.'|'.$this->values->slug($p->nom_complet)] = $p;
        }
    }

    /**
     * Mode tolérant : « D55142 », « D55143 »… sont l'incrémentation automatique d'Excel sur une plaque réelle (« D55141 »).
     * Une plaque inconnue qui ne diffère d'une plaque connue que par un numéro un peu plus grand (jusqu'à 100 d'écart, dans un sens ou dans l'autre) est rattachée
     * à cette plaque, et signalée dans les avertissements pour vérification.
     */
    private function incrementedPlate(string $immat): ?Vehicle
    {
        if (! $this->lenient || ! preg_match('/^(\D*)(\d+)$/', $this->vkey($immat), $m)) {
            return null;
        }
        $best = null;
        $bestGap = 101;
        foreach ($this->vehicles as $key => $vehicle) {
            if (preg_match('/^(\D*)(\d+)$/', (string) $key, $k) && $k[1] === $m[1] && strlen($k[2]) === strlen($m[2])) {
                $gap = abs((int) $m[2] - (int) $k[2]);
                if ($gap >= 1 && $gap < $bestGap) {
                    $best = $vehicle;
                    $bestGap = $gap;
                }
            }
        }
        if ($best) {
            $this->warnings[] = "Plaque « {$immat} » rattachée à {$best->immatriculation} (numéro incrémenté par Excel).";
        }

        return $best;
    }

    private function vkey(string $immat): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $immat));
    }

    private function immat(mixed $value): string
    {
        $immat = Harmonizer::plate((string) $value);
        if (! preg_match('/^[A-Z0-9][A-Z0-9 \-]{2,19}$/', $immat)) {
            throw new ImportException("Immatriculation invalide « {$value} ».");
        }

        return $immat;
    }

    private function vehicle(mixed $immat): Vehicle
    {
        return $this->vehicles[$this->vkey((string) $immat)]
            ?? $this->incrementedPlate((string) $immat)
            ?? throw new ImportException("Véhicule « {$immat} » introuvable : renseignez-le dans l'onglet VEHICULES.");
    }

    private function driver(string $name): Driver
    {
        $key = $this->values->slug($name);

        return $this->drivers[$key] ??= Driver::create([
            'district_id' => $this->district->id, 'nom_complet' => Harmonizer::person($name),
            'matricule' => 'AUTO-'.strtoupper(substr(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()), 0, 10)),
        ]);
    }

    private function personnel(string $name, string $fonction): Personnel
    {
        $key = $fonction.'|'.$this->values->slug($name);

        return $this->personnels[$key] ??= Personnel::create(['district_id' => $this->district->id, 'nom_complet' => Harmonizer::person($name), 'fonction' => $fonction]);
    }

    private function circuit(string $name): Circuit
    {
        return $this->circuits[$this->values->slug($name)] ??= Circuit::create(['district_id' => $this->district->id, 'nom' => Harmonizer::circuitName($name)]);
    }

    private function espc(string $name): Espc
    {
        return $this->espcs[$this->values->slug($name)] ??= Espc::create([
            'district_id' => $this->district->id, 'nom' => Harmonizer::facilityName($name), 'type' => Harmonizer::facilityType($name),
        ]);
    }

    // ------------------------------------------------------------------ conversions

    private function filled(array $row): array
    {
        return array_filter($row, fn ($v) => $v !== null && $v !== '');
    }

    private function date(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($this->isPlaceholder($value)) {
            return null;
        }
        if ($this->lenient && is_string($value) && preg_match('~^\s*(\d{1,2}/\d{1,2}/\d{4})\s*(?:AU|A|-|–|/)\s*\d{1,2}/\d{1,2}/\d{2,4}\s*$~iu', $value, $range)) {
            $this->warnings[] = "{$label} : période « {$value} », début de période retenu ({$range[1]}).";
            $value = $range[1];
        }
        $date = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $this->values->toDate($value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1990 || (int) $m[1] > 2100) {
            if ($this->lenient && $label !== '') {
                $this->warnings[] = "{$label} : date illisible « ".(is_scalar($value) ? $value : $date).' » ignorée.';

                return null;
            }
            throw new ImportException("{$label} : date invalide « ".(is_scalar($value) ? $value : $date).' ».');
        }

        return $date;
    }

    /** Texte saisi à la place d'une valeur (« NA », « non disponible », « - »…). */
    private function isPlaceholder(mixed $value): bool
    {
        return is_string($value) && in_array(Str::lower(Str::ascii(trim($value))), self::PLACEHOLDERS, true);
    }

    /** « 2 800 KG » → 2800 ; « 12,5 » → 12.5 ; le reste est renvoyé tel quel. */
    private function numeric(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $n = str_replace([' ', "\u{a0}", ','], ['', '', '.'], $value);

        return preg_match('/^(-?\d+(?:\.\d+)?)(?:kg|km|l|litres?|ans?)$/i', $n, $m) ? $m[1] : $n;
    }

    /** Ligne d'activité datée hors de la période demandée ? (les vidanges, qui décrivent la dernière en date, ne sont jamais filtrées) */
    private function inPeriod(array $record): bool
    {
        if ($this->period === null || array_key_exists('prochain_km', $record)) {
            return true;
        }
        $date = null;
        foreach (['date', 'debut'] as $field) {
            if (isset($record[$field])) {
                $date = $this->safeDate($record[$field]);
                break;
            }
        }
        if ($date === null) {
            return true;
        }

        return ($this->period[0] === null || $date >= $this->period[0]) && ($this->period[1] === null || $date <= $this->period[1]);
    }

    private function safeDate(mixed $value): ?string
    {
        try {
            return $this->date($value, '');
        } catch (ImportException) {
            return null;
        }
    }

    private function int(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = $this->numeric($value);
        if (! is_numeric($n)) {
            if ($this->isPlaceholder($value)) {
                return null;
            }
            if ($this->lenient) {
                $this->warnings[] = "{$label} : nombre illisible « {$value} » ignoré.";

                return null;
            }
            throw new ImportException("{$label} : nombre invalide « {$value} ».");
        }

        return (int) round((float) $n);
    }

    private function decimal(mixed $value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = $this->numeric($value);
        if (! is_numeric($n)) {
            if ($this->isPlaceholder($value)) {
                return null;
            }
            if ($this->lenient) {
                $this->warnings[] = "{$label} : nombre illisible « {$value} » ignoré.";

                return null;
            }
            throw new ImportException("{$label} : nombre invalide « {$value} ».");
        }

        return (float) $n;
    }

    /** « 15/06/2019 », une vraie date Excel ou simplement « 2019 » → 2019. */
    private function yearOfDateOrYear(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! $value instanceof DateTimeInterface && is_numeric($value) && (float) $value < 3000) {
            return $this->year($value, $label);
        }
        $date = $this->date($value, $label);

        return $date ? (int) substr($date, 0, 4) : null;
    }

    private function year(mixed $value, string $label): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('Y');
        }
        $year = $this->int($value, $label);

        return $year !== null && $year >= 1980 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    private function motif(mixed $label): string
    {
        $slug = $this->values->slug((string) $label);

        return match (true) {
            str_starts_with($slug, 'livraison') => 'distribution',
            str_starts_with($slug, 'redistribution') => 'redistribution',
            str_starts_with($slug, 'enlevement') => 'enlevement_npsp',
            str_starts_with($slug, 'supervision') => 'supervision',
            str_starts_with($slug, 'coaching') => 'coaching',
            default => 'autre',
        };
    }

    private function immobilisationMotif(mixed $label): string
    {
        $slug = $this->values->slug((string) $label);

        return match (true) {
            str_starts_with($slug, 'visite') => 'visite_technique',
            str_starts_with($slug, 'controle') => 'controle_technique',
            str_starts_with($slug, 'reparation') => 'reparation',
            str_starts_with($slug, 'depannage') => 'depannage',
            str_starts_with($slug, 'vidange') => 'vidange',
            str_starts_with($slug, 'incident') => 'incident',
            default => 'autre',
        };
    }

    private function expenseType(mixed $label): string
    {
        $slug = $this->values->slug((string) $label);

        return match (true) {
            str_contains($slug, 'collation') => 'collation',
            str_contains($slug, 'dechargement') => 'dechargement',
            str_contains($slug, 'chargement') => 'chargement',
            str_contains($slug, 'hebergement') => 'hebergement',
            str_contains($slug, 'peage') => 'peage',
            default => 'autre',
        };
    }

    private function carburant(mixed $label): ?string
    {
        $slug = $this->values->slug((string) $label);

        return match (true) {
            $slug === '' => null,
            str_contains($slug, 'gasoil'), str_contains($slug, 'gazole'), str_contains($slug, 'diesel') => 'diesel',
            str_contains($slug, 'essence') => 'essence',
            default => null,
        };
    }

    private function appartenance(mixed $label): ?string
    {
        $slug = $this->values->slug((string) $label);

        return match (true) {
            $slug === '' => null,
            str_starts_with($slug, 'particulier') => 'particulier',
            str_starts_with($slug, 'mutualisation') => 'mutualisation',
            str_starts_with($slug, 'location') => 'location',
            default => 'district',
        };
    }
}
