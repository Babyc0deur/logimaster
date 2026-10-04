<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Circuit extends Model
{
    use HasUuids;

    protected $fillable = [
        'district_id', 'nom', 'distance_totale', 'temps_estime_min', 'frequence', 'statut',
        'point_depart', 'depart_lat', 'depart_lon',
    ];

    protected function casts(): array
    {
        return ['distance_totale' => 'decimal:2', 'depart_lat' => 'float', 'depart_lon' => 'float'];
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    /** Étapes dans l'ordre de passage ; distance_km = distance depuis l'étape précédente. */
    public function espc()
    {
        return $this->belongsToMany(Espc::class, 'circuit_espc')->withPivot(['ordre', 'distance_km'])->orderBy('circuit_espc.ordre');
    }

    public function sorties()
    {
        return $this->hasMany(SortieVehicule::class);
    }

    public function chronogrammes()
    {
        return $this->hasMany(Chronogramme::class);
    }

    /**
     * Étapes ordonnées : site, distance saisie depuis l'étape précédente, distance estimée (GPS, si non saisie) et cumul.
     *
     * @return array<int, array<string, mixed>>
     */
    public function etapesDetail(): array
    {
        $rows = [];
        $prev = $this->depart_lat !== null && $this->depart_lon !== null ? [$this->depart_lat, $this->depart_lon] : null;
        $cumul = 0.0;
        foreach ($this->espc as $i => $e) {
            $declared = $e->pivot->distance_km !== null ? (float) $e->pivot->distance_km : null;
            $estimated = $declared === null && $prev && $e->hasGps() ? \App\Support\Geo::roadEstimateKm($prev[0], $prev[1], $e->gps_lat, $e->gps_lon) : null;
            $cumul += $declared ?? $estimated ?? 0;
            $rows[] = [
                'ordre' => $i + 1, 'nom' => $e->nom, 'type' => $e->type, 'distance' => $declared, 'estimee' => $estimated,
                'cumul' => round($cumul, 1), 'gps' => $e->hasGps(), 'statut' => $e->statut,
            ];
            $prev = $e->hasGps() ? [$e->gps_lat, $e->gps_lon] : $prev;
        }

        return $rows;
    }

    /** Distance estimée (GPS) du dernier site au point de départ : la tournée se termine au district. */
    public function distanceRetour(): ?float
    {
        $last = $this->espc->last();
        if (! $last || ! $last->hasGps() || $this->depart_lat === null || $this->depart_lon === null) {
            return null;
        }

        return \App\Support\Geo::roadEstimateKm($last->gps_lat, $last->gps_lon, $this->depart_lat, $this->depart_lon);
    }

    /** Points à tracer sur la carte : départ puis sites géolocalisés, dans l'ordre de passage. */
    public function mapPoints(): array
    {
        $points = [];
        if ($this->depart_lat !== null && $this->depart_lon !== null) {
            $points[] = ['lat' => $this->depart_lat, 'lon' => $this->depart_lon, 'label' => $this->point_depart ?: 'Départ', 'n' => 'D', 'type' => 'depart'];
        }
        foreach ($this->espc as $i => $e) {
            if ($e->hasGps()) {
                $points[] = ['lat' => $e->gps_lat, 'lon' => $e->gps_lon, 'label' => $e->nom, 'n' => $i + 1, 'type' => 'etape'];
            }
        }

        return $points;
    }

    /** Somme des distances d'étapes renseignées (km), null si aucune. */
    public function getDistanceEtapesAttribute(): ?float
    {
        $legs = $this->espc->pluck('pivot.distance_km')->filter(fn ($d) => $d !== null);

        return $legs->isEmpty() ? null : round((float) $legs->sum(), 2);
    }
}
