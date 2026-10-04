<?php

namespace App\Console\Commands;

use App\Domain\Import\Workbook\WorkbookImporter;
use App\Jobs\ComputeDistrictIndicators;
use Carbon\CarbonImmutable;
use App\Models\District;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Charge les districts depuis un dossier de classeurs Logimaster (un fichier par district et par mois) :
 * associe chaque fichier à un district d'après son nom, garde le classeur le plus récent de chaque district,
 * puis importe les onglets demandés (par défaut : sites ESPC et véhicules). Réimportable sans doublon.
 */
class ImportDistrictWorkbooks extends Command
{
    protected $signature = 'import:districts {folder : Dossier contenant les classeurs (sous-dossiers par mois acceptés)}
        {--sheets=* : Onglets à importer (défaut : « Liste des Sites » et « VEHICULES »)}
        {--from= : Début de la période d\'activité (AAAA-MM-JJ ; défaut : période par défaut de l\'application)}
        {--until= : Fin de la période d\'activité (AAAA-MM-JJ)}
        {--report= : Fichier texte où écrire toutes les lignes ignorées et valeurs illisibles (une ligne par problème)}
        {--activity : Importer aussi l\'activité (chronogramme, sorties, carburant, autres frais, vidanges, immobilisations)}
        {--circuits : Importer uniquement les circuits (nom + sites dans l\'ordre, onglet CHRONOGRAMME), sans planifier de sorties}
        {--only= : Ne traiter que les districts dont le nom contient ce texte}
        {--skip-invalid : Ignorer les lignes en erreur au lieu de refuser le fichier}
        {--dry-run : Afficher l\'association fichiers → districts sans rien importer}';

    protected $description = 'Importe les véhicules et les ESPC de chaque district depuis un dossier de classeurs Logimaster';

    /** Ordre des mois : le plus récent d\'abord. */
    private const MONTHS = ['DECEMBRE', 'NOVEMBRE', 'OCTOBRE', 'SEPTEMBRE', 'AOUT', 'JUILLET', 'JUIN', 'MAI', 'AVRIL', 'MARS', 'FEVRIER', 'JANVIER'];

    public function handle(WorkbookImporter $importer): int
    {
        $folder = (string) $this->argument('folder');
        if (! is_dir($folder)) {
            $this->error("Dossier introuvable : {$folder}");

            return self::FAILURE;
        }

        $districts = District::query()->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'slug' => $this->slug($d->name)]);
        $files = [];
        foreach ((new Finder)->files()->in($folder)->name('/\.(xlsx|xlsm)$/i') as $file) {
            $files[] = $file->getPathname();
        }

        // fichier le plus récent par district
        $best = [];
        $unmatched = [];
        foreach ($files as $path) {
            $match = $this->match($path, $districts);
            if (! $match) {
                $unmatched[] = $path;

                continue;
            }
            $rank = $this->rank($path);
            if (! isset($best[$match['id']]) || $rank > $best[$match['id']]['rank']) {
                $best[$match['id']] = ['rank' => $rank, 'path' => $path, 'district' => $match['name'], 'guess' => $match['guess']];
            }
        }
        ksort($best);

        $only = Str::lower((string) $this->option('only'));
        $best = array_filter($best, fn ($b) => $only === '' || str_contains(Str::lower($b['district']), $only));
        $this->info(count($files).' classeur(s) trouvé(s) → '.count($best).' district(s) retenu(s) (classeur le plus récent de chacun).');

        if ($this->option('dry-run')) {
            $this->table(['District', 'Fichier retenu', 'Association'], array_map(
                fn ($b) => [$b['district'], basename(dirname($b['path'])).'/'.basename($b['path']), $b['guess'] ? 'approchante' : 'exacte'],
                $best
            ));
            $unmatched && $this->warn('Fichiers sans district reconnu : '.implode(' | ', array_map('basename', $unmatched)));

            return self::SUCCESS;
        }

        $circuits = (bool) $this->option('circuits');
        $importer->circuitsOnly($circuits);
        $from = $this->option('from') ?: config('logimaster.default_period.from');
        $until = $this->option('until') ?: config('logimaster.default_period.until');
        $this->option('activity') && $this->line('Activité importée du '.($from ?: '…').' au '.($until ?: '…').'.');
        $importer->forPeriod($this->option('activity') ? $from : null, $this->option('activity') ? $until : null);
        $sheets = $this->option('sheets') ?: ($circuits ? ['CHRONOGRAMME'] : ($this->option('activity') ? array_keys(\App\Domain\Import\Workbook\WorkbookSpec::sheets()) : ['Liste des Sites', 'VEHICULES']));
        $totals = ['created' => 0, 'updated' => 0, 'failed' => 0];
        foreach ($best as $districtId => $b) {
            $this->line("→ {$b['district']} : ".basename($b['path']));
            try {
                $report = $importer->import($b['path'], (string) $districtId, (bool) $this->option('skip-invalid'), $sheets, true);
            } catch (\Throwable $e) {
                $this->error('   échec de lecture : '.$e->getMessage());
                $totals['failed']++;

                continue;
            }
            if (! $report->committed) {
                $this->error('   refusé : '.implode(' | ', $report->errorLines(3)));
                $totals['failed']++;

                continue;
            }
            $this->option('activity') && $this->computeIndicators((string) $districtId, $from, $until);
            $totals['created'] += $report->created();
            $totals['updated'] += $report->updated();
            $this->line("   {$report->created()} créé(s), {$report->updated()} mis à jour".($report->hasErrors() ? ", {$report->errorCount()} ligne(s) ignorée(s)" : ''));
            if ($reportFile = $this->option('report')) {
                file_put_contents($reportFile, "== {$b['district']} — ".basename($b['path'])."\n".implode("\n", [...$report->errorLines(), ...$importer->warnings])."\n", FILE_APPEND);
            }
            foreach (array_slice($importer->warnings, 0, 4) as $warning) {
                $this->line("     <comment>{$warning}</comment>");
            }
            foreach ($report->hasErrors() ? $report->errorLines(5) : [] as $line) {
                $this->line("     <comment>{$line}</comment>");
            }
        }
        $this->info("Terminé : {$totals['created']} créé(s), {$totals['updated']} mis à jour, {$totals['failed']} fichier(s) en échec.");
        $unmatched && $this->warn('Fichiers sans district reconnu : '.implode(' | ', array_map('basename', $unmatched)));

        return $totals['failed'] ? self::FAILURE : self::SUCCESS;
    }

    /** Recalcule les 9 indicateurs de chaque mois de la période pour que les tableaux de bord affichent l'activité importée. */
    private function computeIndicators(string $districtId, ?string $from, ?string $until): void
    {
        $until = $until ? CarbonImmutable::parse($until)->startOfMonth() : CarbonImmutable::now()->startOfMonth();
        $month = ($from ? CarbonImmutable::parse($from) : $until)->startOfMonth();
        $count = 0;
        for (; $month <= $until; $month = $month->addMonth()) {
            dispatch_sync(new ComputeDistrictIndicators($districtId, $month->toDateString()));
            $count++;
        }
        $this->line("   indicateurs recalculés sur {$count} mois");
    }

    private function slug(string $text): string
    {
        return Str::of(Str::ascii($text))->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }

    /** Nom de district déduit du nom de fichier (retire « LogiMaster Version … », DS/DDS, copies, mois). */
    private function nameFromFile(string $path): string
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $name = preg_replace('/logimaster(\s+version\s+\d+)?/i', ' ', $name);
        $name = preg_replace('/\b(nvel\s+version|xlsm?|copie|octobre\s*2025|\d{8})\b/i', ' ', $name);
        $name = preg_replace('/\(\d+\)/', ' ', $name);
        $name = preg_replace('/(^|[\s_-])(dds|ds|dr)(?=[\s_-]|$)/i', ' ', $name);

        $name = preg_replace('/\byop\b/i', 'yopougon', $name);

        return trim(preg_replace('/[\s_-]+/', ' ', $name));
    }

    /** @return array{id: string, name: string, guess: bool}|null */
    private function match(string $path, $districts): ?array
    {
        $fileSlug = $this->slug($this->nameFromFile($path));
        if ($fileSlug === '') {
            return null;
        }
        if ($exact = $districts->firstWhere('slug', $fileSlug)) {
            return ['id' => $exact['id'], 'name' => $exact['name'], 'guess' => false];
        }
        $best = null;
        $bestScore = 0;
        foreach ($districts as $d) {
            similar_text($fileSlug, $d['slug'], $pct);
            $contains = strlen($fileSlug) >= 5 && (str_contains($d['slug'], $fileSlug) || str_contains($fileSlug, $d['slug']));
            $score = $contains ? max($pct, 90) : $pct;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $d;
            }
        }

        return $best && $bestScore >= 80 ? ['id' => $best['id'], 'name' => $best['name'], 'guess' => true] : null;
    }

    /** Plus c'est grand, plus le fichier est récent (mois du dossier) ; les « copies » passent après l'original. */
    private function rank(string $path): int
    {
        $month = Str::upper(Str::ascii(basename(dirname($path))));
        $index = array_search($month, self::MONTHS, true);
        $rank = ($index === false ? 0 : (count(self::MONTHS) - $index)) * 10;

        return $rank - (preg_match('/copie|\(\d+\)/i', basename($path)) ? 1 : 0);
    }
}
