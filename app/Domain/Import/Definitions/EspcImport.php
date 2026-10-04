<?php

namespace App\Domain\Import\Definitions;

use App\Domain\Import\ImportDefinition;
use App\Models\Espc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EspcImport extends ImportDefinition
{
    public function key(): string
    {
        return 'espc';
    }

    public function label(): string
    {
        return 'Établissements sanitaires (ESPC)';
    }

    public function module(): string
    {
        return 'espc';
    }

    public function columns(): array
    {
        return [
            ['name' => 'nom', 'label' => 'Nom', 'required' => true, 'example' => 'CSR GBONOU', 'help' => 'Clé (par district) : un nom existant est mis à jour.'],
            ['name' => 'type', 'label' => 'Type', 'example' => 'centre_sante', 'help' => 'CSR, CSU, maternité, dispensaire…'],
            ['name' => 'gps_lat', 'label' => 'Latitude', 'type' => 'decimal', 'example' => '7.0611'],
            ['name' => 'gps_lon', 'label' => 'Longitude', 'type' => 'decimal', 'example' => '-4.5042'],
            ['name' => 'responsable', 'label' => 'Responsable', 'example' => 'Dr KONAN'],
            ['name' => 'telephone', 'label' => 'Téléphone', 'example' => '+225 05 00 00 00 00'],
            ['name' => 'adresse', 'label' => 'Adresse', 'example' => 'Route de Gbonou, Anyama'],
            ['name' => 'email', 'label' => 'Email', 'example' => 'csr.gbonou@exemple.org'],
            ['name' => 'statut', 'label' => 'Statut', 'example' => 'actif', 'values' => ['actif' => 'Actif', 'inactif' => 'Inactif']],
        ];
    }

    public function rules(string $districtId): array
    {
        return [
            'nom' => ['required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'max:40'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'responsable' => ['nullable', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:160'],
            'statut' => ['nullable', 'in:actif,inactif'],
        ];
    }

    public function save(array $row, string $districtId): string
    {
        $espc = Espc::where('district_id', $districtId)->whereRaw('lower(nom) = ?', [mb_strtolower($row['nom'])])->first();
        $data = $this->filled($row);
        if ($espc) {
            $espc->update($data);

            return 'updated';
        }
        Espc::create($data + ['district_id' => $districtId]);

        return 'created';
    }

    public function toRow(Model $m): array
    {
        return $m->only(['nom', 'type', 'gps_lat', 'gps_lon', 'responsable', 'telephone', 'adresse', 'email', 'statut']);
    }

    public function exportQuery(string $districtId): Builder
    {
        return Espc::where('district_id', $districtId)->orderBy('nom');
    }
}
