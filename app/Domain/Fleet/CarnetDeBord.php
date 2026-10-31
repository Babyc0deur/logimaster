<?php

namespace App\Domain\Fleet;

use App\Models\SortieVehicule;
use App\Models\Vehicle;
use App\Support\SpreadsheetRow;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Carnet de bord d'un véhicule pour un mois, à la présentation du carnet papier :
 * date, chauffeur, départ (lieu, heure, kilométrage), arrivée, distance, motif, carburant et visa du site visité.
 */
class CarnetDeBord
{
    public const HEADERS = [
        'Date', 'Chauffeur', 'Lieu de départ', 'Heure de départ', 'Km de départ (A)', "Lieu d'arrivée", "Heure d'arrivée", "Km d'arrivée (B)",
        'Distance (B − A)', 'Motif', 'Carburant (L)', 'Montant carburant (FCFA)', 'Visa du site visité',
    ];

    /** @return array<string, mixed> */
    public static function build(Vehicle $vehicle, CarbonImmutable $month): array
    {
        $vehicle->loadMissing('district.region');
        $sorties = SortieVehicule::query()->where('vehicle_id', $vehicle->id)->where('statut', '!=', 'annulee')
            ->whereBetween('date_sortie', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->with(['driver:id,nom_complet', 'circuit:id,nom', 'ravitaillements:id,sortie_id,litres,prix_unitaire', 'chronogramme.livraisons.espc:id,nom'])
            ->orderBy('date_sortie')->orderBy('heure_depart')->orderBy('km_depart')->get();

        // Une ligne par tronçon : départ → site 1 → site 2 → … → arrivée, chacun avec son relevé de compteur (A puis B).
        // Le carburant d'une sortie est porté par son premier tronçon, pour n'être compté qu'une fois.
        $rows = [];
        foreach ($sorties as $s) {
            $litres = (float) $s->ravitaillements->sum('litres');
            $montant = (float) $s->ravitaillements->sum(fn ($r) => (float) $r->litres * (float) $r->prix_unitaire);
            $date = $s->date_sortie?->format('d/m/Y');

            $points = [['lieu' => $s->point_depart ?: ($vehicle->district?->name ?? ''), 'km' => $s->km_depart, 'heure' => $s->heure_depart ? substr($s->heure_depart, 0, 5) : '', 'date' => $date]];
            foreach ($s->chronogramme?->livraisons ?? [] as $l) {
                if ($l->statut !== 'planifie' && $l->km_compteur !== null) {
                    $points[] = ['lieu' => $l->espc?->nom ?? '', 'km' => (int) $l->km_compteur, 'heure' => '', 'date' => $l->date_livraison?->format('d/m/Y') ?: $date];
                }
            }
            if ($s->km_arrivee !== null) {
                $points[] = ['lieu' => $s->point_arrivee ?: ($vehicle->district?->name ?? ''), 'km' => $s->km_arrivee, 'heure' => $s->heure_arrivee ? substr($s->heure_arrivee, 0, 5) : '', 'date' => $date];
            }
            if (count($points) === 1) {   // sortie en cours, sans relevé d'arrivée : le départ seul
                $points[] = ['lieu' => $s->point_arrivee ?: ($s->destination ?: ($s->circuit?->nom ?? '')), 'km' => null, 'heure' => $s->heure_arrivee ? substr($s->heure_arrivee, 0, 5) : '', 'date' => $date];
            }

            foreach (array_slice($points, 0, -1, true) as $i => $from) {
                $to = $points[$i + 1];
                $rows[] = [
                    'date' => $from['date'],
                    'chauffeur' => $s->driver?->nom_complet ?? '',
                    'lieu_depart' => $from['lieu'], 'heure_depart' => $from['heure'], 'km_depart' => $from['km'],
                    'lieu_arrivee' => $to['lieu'], 'heure_arrivee' => $to['heure'], 'km_arrivee' => $to['km'],
                    'distance' => $from['km'] !== null && $to['km'] !== null ? $to['km'] - $from['km'] : null,
                    'motif' => SortieVehicule::MOTIFS[$s->motif] ?? (string) $s->motif,
                    'litres' => $i === 0 ? ($litres ?: null) : null,
                    'montant' => $i === 0 ? ($montant ?: null) : null,
                ];
            }
        }

        return [
            'vehicle' => $vehicle,
            'month' => $month,
            'region' => $vehicle->district?->region?->name ?? '',
            'district' => $vehicle->district?->name ?? '',
            'rows' => $rows,
            'totals' => [
                'distance' => (int) collect($rows)->sum('distance'),
                'litres' => (float) collect($rows)->sum('litres'),
                'montant' => (float) collect($rows)->sum('montant'),
            ],
        ];
    }

    public static function filename(Vehicle $vehicle, CarbonImmutable $month, string $ext): string
    {
        return 'carnet-de-bord-'.preg_replace('/[^A-Za-z0-9]+/', '-', (string) $vehicle->immatriculation).'-'.$month->format('Y-m').'.'.$ext;
    }

    public static function pdf(Vehicle $vehicle, CarbonImmutable $month)
    {
        $pdf = Pdf::loadView('exports.carnet-de-bord', self::build($vehicle, $month) + ['headers' => self::HEADERS])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), self::filename($vehicle, $month, 'pdf'));
    }

    public static function xlsx(Vehicle $vehicle, CarbonImmutable $month)
    {
        $data = self::build($vehicle, $month);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(SpreadsheetRow::from(['CARNET DE BORD DU VÉHICULE']));
        $writer->addRow(SpreadsheetRow::from(['Région sanitaire', $data['region'], '', 'District sanitaire', $data['district']]));
        $writer->addRow(SpreadsheetRow::from(['Immatriculation', (string) $vehicle->immatriculation, '', 'Marque', trim($vehicle->marque.' '.$vehicle->modele)]));
        $writer->addRow(SpreadsheetRow::from(['Type de carburant', (string) $vehicle->type_carburant, '', 'Mois', $month->translatedFormat('F Y')]));
        $writer->addRow(SpreadsheetRow::from(['']));
        $writer->addRow(SpreadsheetRow::from(self::HEADERS));
        foreach ($data['rows'] as $r) {
            $writer->addRow(SpreadsheetRow::from([
                $r['date'], $r['chauffeur'], $r['lieu_depart'], $r['heure_depart'], $r['km_depart'], $r['lieu_arrivee'], $r['heure_arrivee'],
                $r['km_arrivee'], $r['distance'], $r['motif'], $r['litres'], $r['montant'], '',
            ]));
        }
        $t = $data['totals'];
        $writer->addRow(SpreadsheetRow::from(['TOTAL', '', '', '', '', '', '', '', $t['distance'], '', $t['litres'], $t['montant'], '']));
        $writer->close();

        return response()->download($path, self::filename($vehicle, $month, 'xlsx'))->deleteFileAfterSend();
    }
}
