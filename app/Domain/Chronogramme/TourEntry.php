<?php

namespace App\Domain\Chronogramme;

use App\Filament\Resources\Sorties\Schemas\SortieForm;
use App\Models\Chronogramme;
use App\Models\Expense;
use App\Models\LivraisonEspc;
use App\Models\Ravitaillement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie d'une tournée complète en une seule fois, au bureau : compteur de départ, statut et kilométrage de chaque site,
 * compteur d'arrivée, plein de carburant et autres frais. Reprend les règles de l'application mobile et du suivi des livraisons
 * (le kilométrage ne recule jamais, une raison est obligatoire pour un site non livré, un prix différent doit être confirmé).
 *
 * Les sites sont réenregistrés tels que saisis (la saisie peut être refaite pour corriger) ; le plein et les frais sont des
 * ajouts : ils ne sont créés que s'ils sont renseignés.
 */
class TourEntry
{
    public const STATUTS = ['planifie' => 'À livrer', 'livre' => 'Livrée sur site', 'transit' => 'Livrée en transit', 'non_livre' => 'Non livrée'];

    /**
     * @param  array{km_depart?: mixed, km_arrivee?: mixed, sites?: array<string, array<string, mixed>>, ravitaillement?: array<string, mixed>|null, frais?: array<int, array<string, mixed>>}  $data
     * @return array{sites: int, plein: bool, frais: int, terminee: bool}
     *
     * @throws ValidationException
     */
    public function apply(Chronogramme $plan, array $data, User $by): array
    {
        $plan->loadMissing(['livraisons', 'sortie', 'vehicle']);
        $fuel = $this->fuel($data['ravitaillement'] ?? null, $plan->vehicle?->currentFuelPrice());
        $frais = $this->frais($data['frais'] ?? []);
        $errors = $this->validate($plan, $data, $fuel, $frais);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $kmDepart = $this->int($data['km_depart'] ?? null);
        $kmArrivee = $this->int($data['km_arrivee'] ?? null);
        $today = CarbonImmutable::today();

        return DB::transaction(function () use ($plan, $data, $by, $fuel, $frais, $kmDepart, $kmArrivee, $today) {
            $sortie = $plan->sortie ?? $plan->demarrer();
            if ($kmDepart !== null && (int) $sortie->km_depart !== $kmDepart) {
                $sortie->update(['km_depart' => $kmDepart]);
            }
            $heures = array_filter(['heure_depart' => $this->hour($data['heure_depart'] ?? null), 'heure_arrivee' => $this->hour($data['heure_arrivee'] ?? null)]);
            if ($heures) {
                $sortie->update($heures);
            }

            $treated = 0;
            foreach ($plan->livraisons as $livraison) {
                $row = $data['sites'][$livraison->id] ?? null;
                if ($row === null) {
                    continue;
                }
                $livraison->update($this->siteValues($livraison, $row, $plan, $by, $today));
                $treated += ($row['statut'] ?? 'planifie') === 'planifie' ? 0 : 1;
            }

            $terminee = false;
            if ($kmArrivee !== null) {
                $sortie->skipSiteDelivery = true;   // les sites non traités restent « à livrer » : rien n'est marqué livré à leur place
                $sortie->update(['km_arrivee' => $kmArrivee]);
                $terminee = true;
            }

            if ($fuel) {
                Ravitaillement::create([
                    'district_id' => $plan->district_id, 'vehicle_id' => $plan->vehicle_id, 'sortie_id' => $sortie->id, 'driver_id' => $plan->driver_id,
                    'litres' => $fuel['litres'], 'prix_unitaire' => $fuel['prix'], 'km_compteur' => $fuel['km'],
                    'station' => $fuel['station'], 'numero_facture' => $fuel['facture'], 'motif' => $plan->motif,
                    'date_ravitaillement' => $this->day($fuel['date'] ?? null, $plan, $today)->toDateString(), 'saisi_par' => $by->getKey(),
                ]);
            }
            foreach ($frais as $f) {
                Expense::create([
                    'district_id' => $plan->district_id, 'sortie_id' => $sortie->id, 'vehicle_id' => $plan->vehicle_id,
                    'type' => $f['type'], 'montant' => $f['montant'], 'motif' => $plan->motif,
                    'beneficiaire' => $f['beneficiaire'], 'commentaire' => $f['commentaire'],
                    'date_depense' => $this->day($f['date'] ?? null, $plan, $today)->toDateString(), 'saisi_par' => $by->getKey(),
                ]);
            }

            return ['sites' => $treated, 'plein' => $fuel !== null, 'frais' => count($frais), 'terminee' => $terminee];
        });
    }

    /** Heure « HH:MM » ou « HH:MM:SS » valide, ramenée à « HH:MM:SS » ; null si vide ou invalide. */
    private function hour(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', trim($value), $m)) {
            return null;
        }

        return $m[1].':'.$m[2].(($m[3] ?? ':00'));
    }

    // ------------------------------------------------------------------ validation

    /** @return array<string, array<int, string>> */
    private function validate(Chronogramme $plan, array $data, ?array $fuel, array $frais): array
    {
        $errors = [];
        $add = function (string $key, string $message) use (&$errors) {
            $errors[$key][] = $message;
        };

        if ($plan->statut === 'annulee') {
            $add('plan_id', 'Cette sortie est annulée.');
        }
        if ($plan->vehicle_id === null) {
            $add('plan_id', 'Affectez un véhicule à cette sortie avant de saisir la tournée.');
        }

        $kmDepart = $this->int($data['km_depart'] ?? null) ?? ($plan->sortie?->km_depart !== null ? (int) $plan->sortie->km_depart : null);
        $kmArrivee = $this->int($data['km_arrivee'] ?? null);
        if ($kmArrivee !== null && $kmDepart !== null && $kmArrivee < $kmDepart) {
            $add('km_arrivee', "Le compteur d'arrivée ({$kmArrivee} km) est inférieur au compteur de départ ({$kmDepart} km).");
        }

        foreach (['heure_depart' => 'de départ', 'heure_arrivee' => "d'arrivée"] as $key => $label) {
            if (filled($data[$key] ?? null) && $this->hour($data[$key]) === null) {
                $add($key, "L'heure {$label} n'est pas valide.");
            }
        }
        $hd = $this->hour($data['heure_depart'] ?? null) ?? $plan->sortie?->heure_depart;
        $ha = $this->hour($data['heure_arrivee'] ?? null);
        if ($hd && $ha && substr($ha, 0, 5) < substr($hd, 0, 5)) {
            $add('heure_arrivee', "L'heure d'arrivée précède l'heure de départ.");
        }

        // le kilométrage ne recule jamais d'un site à l'autre
        $last = $kmDepart;
        foreach ($plan->livraisons as $livraison) {
            $row = $data['sites'][$livraison->id] ?? null;
            if ($row === null) {
                continue;
            }
            $statut = $row['statut'] ?? 'planifie';
            if (! array_key_exists($statut, self::STATUTS)) {
                $add("sites.{$livraison->id}.statut", 'Statut inconnu.');

                continue;
            }
            if ($statut === 'non_livre' && blank($row['raison'] ?? null)) {
                $add("sites.{$livraison->id}.raison", 'La raison de la non-livraison est obligatoire.');
            }
            $km = $statut === 'planifie' ? null : $this->int($row['km'] ?? null);
            if ($km !== null) {
                if ($last !== null && $km < $last) {
                    $add("sites.{$livraison->id}.km", "Le kilométrage ({$km} km) est inférieur au relevé précédent ({$last} km).");
                } else {
                    $last = $km;
                }
            }
            if ($statut !== 'planifie' && filled($row['date'] ?? null) && CarbonImmutable::parse($row['date'])->isFuture()) {
                $add("sites.{$livraison->id}.date", 'La date de livraison ne peut pas être dans le futur.');
            }
        }
        if ($kmArrivee !== null && $last !== null && $kmArrivee < $last) {
            $add('km_arrivee', "Le compteur d'arrivée ({$kmArrivee} km) est inférieur au dernier relevé ({$last} km).");
        }

        if ($fuel) {
            if ($fuel['litres'] === null || $fuel['litres'] < 0.5) {
                $add('ravitaillement.litres', 'Indiquez les litres (0,5 L au minimum).');
            }
            $usual = $fuel['usuel'];
            if ($fuel['prix'] === null) {
                $add('ravitaillement.prix_unitaire', "Aucun prix de carburant n'est défini pour ce véhicule : indiquez le prix du litre.");
            } elseif ($usual !== null && abs($fuel['prix'] - $usual) > 0.001 && ! $fuel['confirme']) {
                $add('ravitaillement.prix_confirme', 'Confirmez le prix du litre, différent du prix du véhicule ('.number_format($usual, 0, ',', ' ').' FCFA).');
            }
            if ($fuel['km'] !== null && $kmDepart !== null && $fuel['km'] < $kmDepart) {
                $add('ravitaillement.km_compteur', "Le compteur du plein ({$fuel['km']} km) est inférieur au compteur de départ ({$kmDepart} km).");
            }
        }

        foreach ($frais as $i => $f) {
            if (! array_key_exists($f['type'], SortieForm::EXPENSE_TYPES)) {
                $add("frais.{$i}.type", 'Type de frais inconnu.');
            }
            if ($f['montant'] === null || $f['montant'] < 1) {
                $add("frais.{$i}.montant", 'Indiquez le montant.');
            }
        }

        return $errors;
    }

    // ------------------------------------------------------------------ valeurs

    /** @return array<string, mixed> */
    private function siteValues(LivraisonEspc $livraison, array $row, Chronogramme $plan, User $by, CarbonImmutable $today): array
    {
        $statut = $row['statut'] ?? 'planifie';
        if ($statut === 'planifie') {
            return ['statut' => 'planifie', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => null, 'km_compteur' => null];
        }

        $km = $this->int($row['km'] ?? null);
        $common = ['km_compteur' => $km, 'saisi_par' => $by->getKey(), 'saisi_at' => now()];

        if ($statut === 'non_livre') {
            return $common + ['statut' => 'non_livre', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => trim((string) $row['raison'])];
        }

        return $common + [
            'statut' => 'livre', 'date_livraison' => $this->day($row['date'] ?? null, $plan, $today)->toDateString(),
            'lieu_livraison' => $statut === 'transit' ? 'transit' : 'site', 'raison_non_livraison' => filled($row['raison'] ?? null) ? trim((string) $row['raison']) : null,
        ];
    }

    /** Date saisie ; à défaut la date prévue de la sortie (jamais plus tard qu'aujourd'hui). */
    private function day(?string $value, Chronogramme $plan, CarbonImmutable $today): CarbonImmutable
    {
        if (filled($value)) {
            return CarbonImmutable::parse($value);
        }
        $planned = CarbonImmutable::parse($plan->date_prevue);

        return $planned->isAfter($today) ? $today : $planned;
    }

    /** @return array{litres: ?float, prix: ?float, usuel: ?float, km: ?int, station: ?string, facture: ?string, confirme: bool, date: ?string}|null */
    private function fuel(?array $row, ?float $usual): ?array
    {
        if (! $row || (blank($row['litres'] ?? null) && blank($row['station'] ?? null) && blank($row['numero_facture'] ?? null))) {
            return null;   // aucun plein saisi
        }

        return [
            'litres' => filled($row['litres'] ?? null) ? (float) $row['litres'] : null,
            'prix' => filled($row['prix_unitaire'] ?? null) ? (float) $row['prix_unitaire'] : $usual,   // sans prix saisi : celui du véhicule
            'usuel' => $usual,
            'km' => $this->int($row['km_compteur'] ?? null),
            'station' => filled($row['station'] ?? null) ? trim((string) $row['station']) : null,
            'facture' => filled($row['numero_facture'] ?? null) ? trim((string) $row['numero_facture']) : null,
            'confirme' => (bool) ($row['prix_confirme'] ?? false),
            'date' => $row['date'] ?? null,
        ];
    }

    /** @return array<int, array{type: string, montant: ?float, beneficiaire: ?string, commentaire: ?string, date: ?string}> */
    private function frais(array $rows): array
    {
        $out = [];
        foreach (array_values($rows) as $row) {
            if (blank($row['montant'] ?? null) && blank($row['beneficiaire'] ?? null)) {
                continue;   // ligne vide
            }
            $out[] = [
                'type' => (string) ($row['type'] ?? 'autre'),
                'montant' => filled($row['montant'] ?? null) ? (float) $row['montant'] : null,
                'beneficiaire' => filled($row['beneficiaire'] ?? null) ? trim((string) $row['beneficiaire']) : null,
                'commentaire' => filled($row['commentaire'] ?? null) ? trim((string) $row['commentaire']) : null,
                'date' => $row['date'] ?? null,
            ];
        }

        return $out;
    }

    private function int(mixed $value): ?int
    {
        return filled($value) && is_numeric($value) ? (int) $value : null;
    }
}
