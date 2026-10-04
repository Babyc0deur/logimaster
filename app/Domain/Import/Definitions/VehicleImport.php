<?php

namespace App\Domain\Import\Definitions;

use App\Domain\Import\ImportDefinition;
use App\Domain\Import\ImportException;
use App\Models\FuelPrice;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VehicleImport extends ImportDefinition
{
    public const STATUTS = ['disponible' => 'Disponible', 'en_mission' => 'En mission', 'en_maintenance' => 'Immobilisé', 'hors_service' => 'Hors service'];

    public function key(): string
    {
        return 'vehicles';
    }

    public function label(): string
    {
        return 'Véhicules';
    }

    public function module(): string
    {
        return 'vehicles';
    }

    public function columns(): array
    {
        return [
            ['name' => 'immatriculation', 'label' => 'Immatriculation', 'required' => true, 'example' => 'D55032', 'help' => 'Clé : une immatriculation existante est mise à jour.'],
            ['name' => 'marque', 'label' => 'Marque', 'example' => 'FORD'],
            ['name' => 'modele', 'label' => 'Modèle', 'example' => 'FOURGON'],
            ['name' => 'type_vehicule', 'label' => 'Type de véhicule', 'example' => 'fourgon', 'help' => 'Fourgon, camion, 4x4, moto…'],
            ['name' => 'type_carburant', 'label' => 'Type de carburant', 'example' => 'diesel', 'values' => FuelPrice::TYPES],
            ['name' => 'consommation_theorique', 'label' => 'Consommation théorique (L/100km)', 'type' => 'decimal', 'example' => '15'],
            ['name' => 'annee_circulation', 'label' => 'Année de mise en circulation', 'type' => 'int', 'example' => '2019'],
            ['name' => 'poids_vide', 'label' => 'Poids à vide (kg)', 'type' => 'int', 'example' => '2100'],
            ['name' => 'capacite_charge', 'label' => 'Capacité de charge (kg)', 'type' => 'int', 'example' => '1500'],
            ['name' => 'volume_utile', 'label' => 'Volume utile (m³)', 'type' => 'decimal', 'example' => '9.5'],
            ['name' => 'appartenance', 'label' => 'Appartenance', 'example' => 'district', 'values' => Vehicle::APPARTENANCES],
            ['name' => 'bailleur', 'label' => 'Bailleur', 'example' => 'UCP FM'],
            ['name' => 'date_reception', 'label' => 'Date de réception', 'type' => 'date', 'example' => '2019-06-15', 'help' => 'AAAA-MM-JJ ou JJ/MM/AAAA'],
            ['name' => 'km_actuel', 'label' => 'Kilométrage actuel', 'type' => 'int', 'example' => '71342'],
            ['name' => 'km_vidange', 'label' => 'Kilométrage prochaine vidange', 'type' => 'int', 'example' => '75000'],
            ['name' => 'date_ct', 'label' => 'Expiration contrôle technique', 'type' => 'date', 'example' => '2026-03-31'],
            ['name' => 'date_assurance', 'label' => 'Expiration assurance', 'type' => 'date', 'example' => '2026-06-30'],
            ['name' => 'statut', 'label' => 'Statut', 'example' => 'disponible', 'values' => self::STATUTS],
        ];
    }

    public function rules(string $districtId): array
    {
        return [
            'immatriculation' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{2,19}$/'],
            'marque' => ['nullable', 'string', 'max:60'],
            'modele' => ['nullable', 'string', 'max:60'],
            'type_vehicule' => ['nullable', 'string', 'max:40'],
            'type_carburant' => ['nullable', 'in:'.implode(',', array_keys(FuelPrice::TYPES))],
            'consommation_theorique' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'annee_circulation' => ['nullable', 'integer', 'min:1980', 'max:'.date('Y')],
            'poids_vide' => ['nullable', 'integer', 'min:0'],
            'capacite_charge' => ['nullable', 'integer', 'min:0'],
            'volume_utile' => ['nullable', 'numeric', 'min:0'],
            'appartenance' => ['nullable', 'in:'.implode(',', array_keys(Vehicle::APPARTENANCES))],
            'bailleur' => ['nullable', 'string', 'max:120'],
            'date_reception' => ['nullable', 'date', 'before_or_equal:today'],
            'km_actuel' => ['nullable', 'integer', 'min:0'],
            'km_vidange' => ['nullable', 'integer', 'min:0'],
            'date_ct' => ['nullable', 'date'],
            'date_assurance' => ['nullable', 'date'],
            'statut' => ['nullable', 'in:'.implode(',', array_keys(self::STATUTS))],
        ];
    }

    public function save(array $row, string $districtId): string
    {
        $row['immatriculation'] = strtoupper(trim($row['immatriculation']));
        $vehicle = Vehicle::withTrashed()->where('immatriculation', $row['immatriculation'])->first();
        if ($vehicle && $vehicle->district_id !== $districtId) {
            throw new ImportException("L'immatriculation {$row['immatriculation']} appartient à un autre district.");
        }

        $data = $this->filled($row);
        if ($vehicle) {
            $vehicle->trashed() && $vehicle->restore();
            $vehicle->update($data + ['version' => $vehicle->version + 1]);

            return 'updated';
        }
        Vehicle::create($data + ['district_id' => $districtId]);

        return 'created';
    }

    public function toRow(Model $m): array
    {
        $row = [];
        foreach ($this->headers() as $name) {
            $v = $m->{$name};
            $row[$name] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }

        return $row;
    }

    public function exportQuery(string $districtId): Builder
    {
        return Vehicle::where('district_id', $districtId)->orderBy('immatriculation');
    }
}
