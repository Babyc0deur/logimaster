<?php

namespace App\Domain\Import\Workbook;

use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Espc;
use App\Models\Expense;
use App\Models\FuelPrice;
use App\Models\Immobilisation;
use App\Models\Ravitaillement;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Models\Vidange;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Génère un classeur .xlsx avec les mêmes onglets et en-têtes que le modèle Excel Logimaster :
 * vide (modèle à remplir) ou rempli avec les données d'un district (sauvegarde / migration).
 * Mise en page identique à l'original : ligne 1 = titre, ligne 2 = en-têtes, colonne A réservée à l'index.
 */
class WorkbookExporter
{
    public function template(): string
    {
        return $this->write(null);
    }

    public function export(string $districtId): string
    {
        return $this->write(District::findOrFail($districtId));
    }

    private function write(?District $district): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lmw').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $first = true;
        foreach (WorkbookSpec::sheets() as $name => $spec) {
            $first ? $writer->getCurrentSheet()->setName($name) : $writer->addNewSheetAndMakeItCurrent()->setName($name);
            $first = false;

            $writer->addRow(Row::fromValues(['', $district ? "{$name} — {$district->name}" : "{$name} — modèle d'import Logimaster (ne pas modifier la ligne 2)"]));
            $writer->addRow(Row::fromValues(['', ...$spec['headers']]));

            $rows = $district ? $this->rows($name, $district) : [];
            foreach ($rows as $i => $row) {
                $writer->addRow(Row::fromValues([$i + 1, ...$row]));
            }
        }
        $writer->close();

        return $path;
    }

    /** @return array<int, array<int, mixed>> */
    private function rows(string $sheet, District $d): array
    {
        $id = $d->id;
        $motif = fn (?string $key) => array_search($key, WorkbookSpec::MOTIFS, true) ?: null;
        $date = fn ($v) => $v?->format('Y-m-d');

        return match ($sheet) {
            'Liste des Sites' => Espc::where('district_id', $id)->orderBy('nom')->pluck('nom')->map(fn ($n) => [$n])->all(),

            'VEHICULES' => Vehicle::where('district_id', $id)->orderBy('immatriculation')->get()->map(fn (Vehicle $v) => [
                $v->immatriculation, $d->name, $date($v->date_reception), $v->bailleur, $v->marque, $v->modele, $v->vignette_annee,
                $v->annee_circulation, $date($v->date_assurance), Vehicle::APPARTENANCES[$v->appartenance] ?? $v->appartenance,
                FuelPrice::TYPES[$v->type_carburant] ?? $v->type_carburant, $date($v->date_dernier_ct), $date($v->date_ct),
                $v->km_vidange, $v->poids_vide, $v->commentaire,
            ])->all(),

            // Une ligne par site visité, comme dans le classeur d'origine.
            'CHRONOGRAMME' => Chronogramme::with(['circuit:id,nom', 'espc:id,nom'])->where('district_id', $id)->orderBy('date_prevue')->get()
                ->flatMap(function (Chronogramme $p) use ($motif) {
                    $base = [$p->date_prevue->copy()->startOfMonth()->format('Y-m-d'), $motif($p->motif), $p->circuit?->nom];
                    $sites = $p->espc->isEmpty() ? collect([null]) : $p->espc->pluck('nom');

                    return $sites->map(fn ($site) => [...$base, $site, $p->date_prevue->format('Y-m-d'), $p->commentaires]);
                })->all(),

            'CIRCUITS' => SortieVehicule::with(['vehicle:id,immatriculation', 'driver:id,nom_complet', 'chefMission:id,nom_complet', 'passagers:id,nom_complet', 'circuit:id,nom'])
                ->where('district_id', $id)->orderBy('date_sortie')->get()->map(fn (SortieVehicule $s) => [
                    $s->vehicle?->immatriculation, $date($s->date_sortie), $s->driver?->nom_complet, $s->chefMission?->nom_complet,
                    $s->passagers[0]->nom_complet ?? null, $s->passagers[1]->nom_complet ?? null, $s->passagers[2]->nom_complet ?? null,
                    $s->circuit?->nom, $s->point_depart, $s->km_depart, $s->point_arrivee, $s->km_arrivee, $motif($s->motif), $s->distance, $s->commentaires,
                ])->all(),

            'CARBURANT' => Ravitaillement::with(['vehicle:id,immatriculation,type_carburant', 'driver:id,nom_complet', 'sortie:id,motif'])
                ->where('district_id', $id)->orderBy('date_ravitaillement')->get()->map(fn (Ravitaillement $r) => [
                    $r->vehicle?->immatriculation, $r->driver?->nom_complet, $motif($r->motif ?? $r->sortie?->motif),
                    FuelPrice::TYPES[$r->vehicle?->type_carburant] ?? null, $date($r->date_ravitaillement), $r->km_compteur,
                    (float) $r->litres, (float) $r->prix_unitaire, $r->montant_total, $r->numero_facture, null,
                ])->all(),

            'AUTRES FRAIS' => Expense::with('vehicle:id,immatriculation')->where('district_id', $id)->orderBy('date_depense')->get()
                ->map(fn (Expense $e) => [
                    $date($e->date_depense), $e->beneficiaire, $e->vehicle?->immatriculation, $motif($e->motif),
                    array_search($e->type, WorkbookSpec::EXPENSE_TYPES, true) ?: $e->type, (float) $e->montant, $e->commentaire,
                ])->all(),

            'VIDANGES' => Vidange::with('vehicle:id,immatriculation')->where('district_id', $id)->orderBy('date')->get()
                ->map(fn (Vidange $v) => [
                    $v->vehicle?->immatriculation, $date($v->date), $v->km, $v->numero_facture, $v->montant !== null ? (float) $v->montant : null,
                    $v->prochain_km, $v->observations,
                ])->all(),

            'IMMOBILISATION' => Immobilisation::with('vehicle:id,immatriculation')->where('district_id', $id)->orderBy('date_debut')->get()
                ->map(fn (Immobilisation $i) => [
                    $i->vehicle?->immatriculation, $i->date_debut->copy()->startOfMonth()->format('Y-m-d'), $date($i->date_debut),
                    array_search($i->motif, WorkbookSpec::IMMOBILISATION_MOTIFS, true) ?: $i->motif,
                    $i->montant !== null ? (float) $i->montant : null, $i->prestataire, $date($i->date_fin), $i->description,
                ])->all(),

            default => [],
        };
    }
}
