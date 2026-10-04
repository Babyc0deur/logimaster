<?php

namespace App\Domain\Import\Definitions;

use App\Domain\Import\ImportDefinition;
use App\Domain\Import\ImportException;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DriverImport extends ImportDefinition
{
    public const STATUTS = Driver::STATUTS;

    public function key(): string
    {
        return 'drivers';
    }

    public function label(): string
    {
        return 'Chauffeurs';
    }

    public function module(): string
    {
        return 'drivers';
    }

    public function columns(): array
    {
        return [
            ['name' => 'matricule', 'label' => 'Matricule', 'required' => true, 'example' => 'CH-001', 'help' => 'Clé : un matricule existant est mis à jour.'],
            ['name' => 'nom_complet', 'label' => 'Nom complet', 'required' => true, 'example' => 'ABRAHAM Kouassi'],
            ['name' => 'telephone', 'label' => 'Téléphone', 'example' => '+225 07 00 00 00 00'],
            ['name' => 'categorie_permis', 'label' => 'Catégorie de permis', 'example' => 'B', 'help' => 'B, C, D…'],
            ['name' => 'permis_expiration', 'label' => "Expiration du permis", 'type' => 'date', 'example' => '2027-05-20', 'help' => 'AAAA-MM-JJ ou JJ/MM/AAAA'],
            ['name' => 'email', 'label' => 'Email', 'example' => 'abraham@exemple.org'],
            ['name' => 'numero_permis', 'label' => 'Numéro de permis', 'example' => 'CI-123456'],
            ['name' => 'date_obtention_permis', 'label' => "Date d'obtention du permis", 'type' => 'date', 'example' => '2012-03-10'],
            ['name' => 'statut', 'label' => 'Statut', 'example' => 'actif', 'values' => self::STATUTS],
        ];
    }

    public function rules(string $districtId): array
    {
        return [
            'matricule' => ['required', 'string', 'max:30'],
            'nom_complet' => ['required', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'categorie_permis' => ['nullable', 'string', 'max:10'],
            'permis_expiration' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:160'],
            'numero_permis' => ['nullable', 'string', 'max:40'],
            'date_obtention_permis' => ['nullable', 'date'],
            'statut' => ['nullable', 'in:'.implode(',', array_keys(self::STATUTS))],
        ];
    }

    public function save(array $row, string $districtId): string
    {
        $driver = Driver::withTrashed()->where('matricule', $row['matricule'])->first();
        if ($driver && $driver->district_id !== $districtId) {
            throw new ImportException("Le matricule {$row['matricule']} appartient à un autre district.");
        }
        $data = $this->filled($row);
        if ($driver) {
            $driver->trashed() && $driver->restore();
            $driver->update($data + ['version' => $driver->version + 1]);

            return 'updated';
        }
        Driver::create($data + ['district_id' => $districtId]);

        return 'created';
    }

    public function toRow(Model $m): array
    {
        return [
            'matricule' => $m->matricule, 'nom_complet' => $m->nom_complet, 'telephone' => $m->telephone,
            'categorie_permis' => $m->categorie_permis, 'permis_expiration' => $m->permis_expiration?->format('Y-m-d'),
            'email' => $m->email, 'numero_permis' => $m->numero_permis, 'date_obtention_permis' => $m->date_obtention_permis?->format('Y-m-d'), 'statut' => $m->statut,
        ];
    }

    public function exportQuery(string $districtId): Builder
    {
        return Driver::where('district_id', $districtId)->orderBy('nom_complet');
    }
}
