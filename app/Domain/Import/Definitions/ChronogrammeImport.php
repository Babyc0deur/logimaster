<?php

namespace App\Domain\Import\Definitions;

use App\Domain\Import\ImportDefinition;
use App\Domain\Import\ImportException;
use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChronogrammeImport extends ImportDefinition
{
    public const MOTIFS = [
        'distribution' => 'Livraison ESPC', 'redistribution' => 'Redistribution', 'enlevement_npsp' => 'Enlèvement NPSP',
        'supervision' => 'Supervision', 'coaching' => 'Coaching', 'autre' => 'Autres',
    ];

    public function key(): string
    {
        return 'chronogrammes';
    }

    public function label(): string
    {
        return 'Chronogramme (sorties planifiées)';
    }

    public function module(): string
    {
        return 'chronogrammes';
    }

    public function columns(): array
    {
        return [
            ['name' => 'date_prevue', 'label' => 'Date prévue', 'required' => true, 'type' => 'date', 'example' => '2026-10-12', 'help' => 'AAAA-MM-JJ ou JJ/MM/AAAA'],
            ['name' => 'heure_depart', 'label' => 'Heure de départ', 'type' => 'time', 'example' => '07:30'],
            ['name' => 'vehicule', 'label' => 'Véhicule (immatriculation)', 'example' => 'D55032', 'help' => 'Facultatif (affecté plus tard) ; doit exister dans le district.'],
            ['name' => 'chauffeur', 'label' => 'Chauffeur (matricule)', 'example' => 'CH-001', 'help' => 'Doit exister dans le district.'],
            ['name' => 'circuit', 'label' => 'Circuit (nom)', 'example' => 'CIRCUIT 1', 'help' => 'Doit exister dans le district.'],
            ['name' => 'motif', 'label' => 'Motif', 'example' => 'distribution', 'values' => self::MOTIFS],
            ['name' => 'destination', 'label' => 'Destination', 'example' => 'CIRCUIT 1'],
            ['name' => 'statut', 'label' => 'Statut', 'example' => 'planifiee', 'values' => Chronogramme::STATUTS],
            ['name' => 'commentaires', 'label' => 'Commentaires', 'example' => ''],
        ];
    }

    public function notes(): array
    {
        return [
            'Chargez d\'abord les véhicules, chauffeurs et circuits : le chronogramme y fait référence.',
            'Clé : véhicule + date + circuit. Un véhicule ne peut pas avoir deux sorties non annulées le même jour.',
        ];
    }

    public function rules(string $districtId): array
    {
        return [
            'date_prevue' => ['required', 'date'],
            'heure_depart' => ['nullable', 'date_format:H:i'],
            'vehicule' => ['nullable', 'string'],
            'chauffeur' => ['nullable', 'string'],
            'circuit' => ['nullable', 'string'],
            'motif' => ['nullable', 'in:'.implode(',', array_keys(self::MOTIFS))],
            'destination' => ['nullable', 'string', 'max:160'],
            'statut' => ['nullable', 'in:'.implode(',', array_keys(Chronogramme::STATUTS))],
            'commentaires' => ['nullable', 'string'],
        ];
    }

    public function save(array $row, string $districtId): string
    {
        $vehicle = null;
        if (! empty($row['vehicule'])) {
            $vehicle = Vehicle::where('district_id', $districtId)->where('immatriculation', strtoupper(trim($row['vehicule'])))->first()
                ?? throw new ImportException("Véhicule « {$row['vehicule']} » introuvable dans ce district.");
        }
        $driver = null;
        if (! empty($row['chauffeur'])) {
            $driver = Driver::where('district_id', $districtId)->where('matricule', $row['chauffeur'])->first()
                ?? throw new ImportException("Chauffeur « {$row['chauffeur']} » introuvable dans ce district.");
        }
        $circuit = null;
        if (! empty($row['circuit'])) {
            $circuit = Circuit::where('district_id', $districtId)->whereRaw('lower(nom) = ?', [mb_strtolower($row['circuit'])])->first()
                ?? throw new ImportException("Circuit « {$row['circuit']} » introuvable dans ce district.");
        }

        $statut = $row['statut'] ?? 'planifiee';
        $existing = Chronogramme::where('district_id', $districtId)->where('vehicle_id', $vehicle?->id)
            ->whereDate('date_prevue', $row['date_prevue'])->where('circuit_id', $circuit?->id)->first();

        if (! $existing && $vehicle && $statut !== 'annulee' && Chronogramme::where('vehicle_id', $vehicle->id)
            ->whereDate('date_prevue', $row['date_prevue'])->where('statut', '!=', 'annulee')->exists()) {
            throw new ImportException("Le véhicule {$vehicle->immatriculation} est déjà planifié le {$row['date_prevue']}.");
        }

        $data = $this->filled([
            'date_prevue' => $row['date_prevue'], 'heure_depart' => $row['heure_depart'] ?? null,
            'driver_id' => $driver?->id, 'motif' => $row['motif'] ?? null, 'destination' => $row['destination'] ?? null,
            'statut' => $row['statut'] ?? null, 'commentaires' => $row['commentaires'] ?? null,
        ]);

        if ($existing) {
            $existing->update($data);

            return 'updated';
        }
        Chronogramme::create($data + ['district_id' => $districtId, 'vehicle_id' => $vehicle?->id, 'circuit_id' => $circuit?->id]);

        return 'created';
    }

    public function toRow(Model $m): array
    {
        return [
            'date_prevue' => $m->date_prevue->format('Y-m-d'), 'heure_depart' => $m->heure_depart ? substr($m->heure_depart, 0, 5) : null,
            'vehicule' => $m->vehicle?->immatriculation, 'chauffeur' => $m->driver?->matricule, 'circuit' => $m->circuit?->nom,
            'motif' => $m->motif, 'destination' => $m->destination, 'statut' => $m->statut, 'commentaires' => $m->commentaires,
        ];
    }

    public function exportQuery(string $districtId): Builder
    {
        return Chronogramme::with(['vehicle:id,immatriculation', 'driver:id,matricule', 'circuit:id,nom'])
            ->where('district_id', $districtId)->orderBy('date_prevue');
    }
}
