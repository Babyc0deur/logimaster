<?php

namespace App\Domain\Import\Definitions;

use App\Domain\Import\ImportDefinition;
use App\Models\Circuit;
use App\Models\Espc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CircuitImport extends ImportDefinition
{
    public const FREQUENCES = ['quotidien' => 'Quotidien', 'hebdomadaire' => 'Hebdomadaire', 'bimensuel' => 'Bimensuel', 'mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel'];

    public function key(): string
    {
        return 'circuits';
    }

    public function label(): string
    {
        return 'Circuits';
    }

    public function module(): string
    {
        return 'circuits';
    }

    public function columns(): array
    {
        return [
            ['name' => 'nom', 'label' => 'Nom du circuit', 'required' => true, 'example' => 'CIRCUIT 1', 'help' => 'Clé (par district) : un nom existant est mis à jour.'],
            ['name' => 'distance_totale', 'label' => 'Distance totale (km)', 'type' => 'decimal', 'example' => '387'],
            ['name' => 'temps_estime_min', 'label' => 'Temps estimé (minutes)', 'type' => 'int', 'example' => '360'],
            ['name' => 'frequence', 'label' => 'Fréquence', 'example' => 'hebdomadaire', 'values' => self::FREQUENCES],
            ['name' => 'statut', 'label' => 'Statut', 'example' => 'actif', 'values' => ['actif' => 'Actif', 'inactif' => 'Inactif']],
            ['name' => 'point_depart', 'label' => 'Point de départ', 'example' => 'DDKM Anyama'],
            ['name' => 'depart_lat', 'label' => 'Latitude du départ', 'type' => 'decimal', 'example' => '5.4950'],
            ['name' => 'depart_lon', 'label' => 'Longitude du départ', 'type' => 'decimal', 'example' => '-4.0510'],
            ['name' => 'espc', 'label' => 'ESPC desservis (dans l\'ordre)', 'example' => 'CSR GBONOU; MATERNITE YAKOUASSIKRO; CSR KOFFI ADOUKRO',
                'help' => 'Noms séparés par « ; », dans l\'ordre des étapes. Les ESPC inconnus sont créés automatiquement.'],
            ['name' => 'distances_etapes', 'label' => 'Distances des étapes (km)', 'example' => '12; 7.5; 20',
                'help' => 'Une distance par étape, séparées par « ; » : depuis l\'étape précédente (ou le point de départ). Vide = estimée via le GPS.'],
        ];
    }

    public function notes(): array
    {
        return ['Les ESPC listés remplacent la liste d\'étapes actuelle du circuit. Laissez la cellule vide pour ne pas la modifier.'];
    }

    public function rules(string $districtId): array
    {
        return [
            'nom' => ['required', 'string', 'max:120'],
            'distance_totale' => ['nullable', 'numeric', 'min:0'],
            'temps_estime_min' => ['nullable', 'integer', 'min:0'],
            'frequence' => ['nullable', 'in:'.implode(',', array_keys(self::FREQUENCES))],
            'statut' => ['nullable', 'in:actif,inactif'],
            'espc' => ['nullable', 'string'],
            'point_depart' => ['nullable', 'string', 'max:160'],
            'depart_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'depart_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'distances_etapes' => ['nullable', 'string', 'regex:/^\s*\d+([.,]\d+)?\s*(;\s*\d+([.,]\d+)?\s*)*;?\s*$/'],
        ];
    }

    public function save(array $row, string $districtId): string
    {
        $names = collect(explode(';', (string) ($row['espc'] ?? '')))->map(fn ($n) => trim($n))->filter()->unique(fn ($n) => mb_strtolower($n))->values();
        $distances = collect(explode(';', (string) ($row['distances_etapes'] ?? '')))->map(fn ($d) => trim($d))->filter(fn ($d) => $d !== '')
            ->map(fn ($d) => (float) str_replace(',', '.', $d))->values();
        unset($row['espc'], $row['distances_etapes']);

        $circuit = Circuit::where('district_id', $districtId)->whereRaw('lower(nom) = ?', [mb_strtolower($row['nom'])])->first();
        $data = $this->filled($row);
        $result = 'updated';
        if ($circuit) {
            $circuit->update($data);
        } else {
            $circuit = Circuit::create($data + ['district_id' => $districtId]);
            $result = 'created';
        }

        if ($names->isNotEmpty()) {
            $sync = [];
            foreach ($names as $i => $name) {
                $espc = Espc::where('district_id', $districtId)->whereRaw('lower(nom) = ?', [mb_strtolower($name)])->first()
                    ?? Espc::create(['district_id' => $districtId, 'nom' => $name]);
                $sync[$espc->id] = ['ordre' => $i + 1, 'distance_km' => $distances[$i] ?? null];
            }
            $circuit->espc()->sync($sync);
        }

        return $result;
    }

    public function toRow(Model $m): array
    {
        return [
            'nom' => $m->nom, 'distance_totale' => $m->distance_totale, 'temps_estime_min' => $m->temps_estime_min,
            'frequence' => $m->frequence, 'statut' => $m->statut, 'espc' => $m->espc->pluck('nom')->join('; '),
            'point_depart' => $m->point_depart, 'depart_lat' => $m->depart_lat, 'depart_lon' => $m->depart_lon,
            'distances_etapes' => $m->espc->map(fn ($e) => $e->pivot->distance_km)->contains(fn ($d) => $d !== null)
                ? $m->espc->map(fn ($e) => $e->pivot->distance_km !== null ? (float) $e->pivot->distance_km : 0)->join('; ') : null,
        ];
    }

    public function exportQuery(string $districtId): Builder
    {
        return Circuit::with('espc')->where('district_id', $districtId)->orderBy('nom');
    }
}
