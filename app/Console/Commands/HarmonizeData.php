<?php

namespace App\Console\Commands;

use App\Domain\Import\Harmonizer;
use App\Models\Circuit;
use App\Models\Espc;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Met en forme les données déjà chargées pour qu'elles correspondent aux conventions de LogiMaster :
 * marques et modèles, bailleurs, plaques, noms et types d'ESPC, noms de circuits ; fusionne les ESPC en double d'un district.
 */
class HarmonizeData extends Command
{
    protected $signature = 'data:harmonize {--dry-run : Afficher ce qui changerait sans rien écrire}';

    protected $description = 'Harmonise véhicules, ESPC et circuits (écritures uniques) et fusionne les doublons';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $stats = ['vehicules' => 0, 'plaques' => 0, 'espc' => 0, 'types' => 0, 'doublons' => 0, 'circuits' => 0, 'circuits_fusionnes' => 0, 'circuits_retires' => 0, 'espc_retires' => 0];

        DB::transaction(function () use ($dry, &$stats) {
            foreach (Vehicle::withTrashed()->get() as $v) {
                [$marque, $modele] = Harmonizer::vehicle($v->marque, $v->modele);
                $data = ['marque' => $marque, 'modele' => $modele, 'bailleur' => Harmonizer::funder($v->bailleur)];
                $plate = Harmonizer::plate($v->immatriculation);
                if ($plate !== '' && $plate !== $v->immatriculation && ! Vehicle::withTrashed()->where('immatriculation', $plate)->whereKeyNot($v->getKey())->exists()) {
                    $data['immatriculation'] = $plate;
                    $stats['plaques']++;
                }
                $v->fill($data);
                if ($v->isDirty()) {
                    $stats['vehicules']++;
                    $dry || $v->save();
                }
            }

            // ESPC : écriture et type, puis fusion des doublons (même district, même nom une fois harmonisé)
            $seen = [];
            foreach (Espc::orderBy('created_at')->orderBy('id')->get() as $e) {
                if (Harmonizer::isPlaceholderSite($e->nom)) {
                    $stats['espc_retires']++;
                    $dry || $e->delete();

                    continue;
                }
                $name = Harmonizer::facilityName($e->nom);
                $key = $e->district_id.'|'.Harmonizer::key($name);
                if (isset($seen[$key])) {
                    $stats['doublons']++;
                    $dry || $this->merge($e, $seen[$key]);

                    continue;
                }
                $seen[$key] = $e;
                $type = $e->type && $e->type !== 'centre_sante' ? $e->type : (Harmonizer::facilityType($name) ?? $e->type);
                $e->fill(['nom' => $name, 'type' => $type]);
                if ($e->isDirty('nom')) {
                    $stats['espc']++;
                }
                if ($e->isDirty('type')) {
                    $stats['types']++;
                }
                $dry || $e->save();
            }

            $kept = [];
            foreach (Circuit::withCount('espc')->orderBy('created_at')->orderBy('id')->get() as $c) {
                $name = Harmonizer::circuitName($c->nom);
                $used = DB::table('chronogrammes')->where('circuit_id', $c->getKey())->exists() || DB::table('sorties_vehicules')->where('circuit_id', $c->getKey())->exists();
                if (! $used && ! Harmonizer::isRoute($name, $c->espc_count)) {
                    $stats['circuits_retires']++;
                    $dry || $c->delete();

                    continue;
                }
                $key = $c->district_id.'|'.Harmonizer::key($name);
                if (isset($kept[$key])) {
                    $stats['circuits_fusionnes']++;
                    $dry || $this->mergeCircuit($c, $kept[$key]);

                    continue;
                }
                $kept[$key] = $c;
                if ($c->nom !== $name) {
                    $stats['circuits']++;
                    $dry || $c->update(['nom' => $name]);
                }
            }
        });

        $this->info(($dry ? '[simulation] ' : '')."Véhicules harmonisés : {$stats['vehicules']} (dont {$stats['plaques']} plaque(s)) · ESPC renommés : {$stats['espc']} · types renseignés : {$stats['types']} · doublons fusionnés : {$stats['doublons']} · circuits renommés : {$stats['circuits']}, fusionnés : {$stats['circuits_fusionnes']}, retirés (motifs, destinations) : {$stats['circuits_retires']} · libellés du modèle retirés des ESPC : {$stats['espc_retires']}");

        return self::SUCCESS;
    }

    /** Reporte sites (à la suite), sorties planifiées et sorties de $duplicate sur $keep, puis supprime $duplicate. */
    private function mergeCircuit(Circuit $duplicate, Circuit $keep): void
    {
        $known = DB::table('circuit_espc')->where('circuit_id', $keep->getKey())->pluck('espc_id')->all();
        $order = (int) DB::table('circuit_espc')->where('circuit_id', $keep->getKey())->max('ordre');
        foreach (DB::table('circuit_espc')->where('circuit_id', $duplicate->getKey())->orderBy('ordre')->get() as $row) {
            if (! in_array($row->espc_id, $known, true)) {
                DB::table('circuit_espc')->insert(['circuit_id' => $keep->getKey(), 'espc_id' => $row->espc_id, 'ordre' => ++$order, 'distance_km' => $row->distance_km ?? null]);
            }
        }
        DB::table('chronogrammes')->where('circuit_id', $duplicate->getKey())->update(['circuit_id' => $keep->getKey()]);
        DB::table('sorties_vehicules')->where('circuit_id', $duplicate->getKey())->update(['circuit_id' => $keep->getKey()]);
        $duplicate->delete();
    }

    /** Reporte les circuits et livraisons de $duplicate sur $keep, puis supprime $duplicate. */
    private function merge(Espc $duplicate, Espc $keep): void
    {
        foreach (['circuit_espc' => 'circuit_id', 'livraisons_espc' => 'chronogramme_id'] as $table => $column) {
            $already = DB::table($table)->where('espc_id', $keep->getKey())->pluck($column)->all();
            DB::table($table)->where('espc_id', $duplicate->getKey())->whereIn($column, $already)->delete();
            DB::table($table)->where('espc_id', $duplicate->getKey())->update(['espc_id' => $keep->getKey()]);
        }
        $duplicate->delete();
    }
}
