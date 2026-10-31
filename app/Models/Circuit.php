<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Circuit extends Model
{
    use HasUuids;

    public const SAISONS = ['toute_annee' => "Toute l'année", 'seche' => 'Saison sèche', 'pluvieuse' => 'Saison pluvieuse'];

    public const VERSIONS = ['existant' => 'Existant', 'optimise' => 'Optimisé'];

    /** Durée maximale d'une tournée (minutes) : au-delà, le circuit déclenche une alerte. */
    public const DUREE_MAX_MIN = 480;

    protected $fillable = [
        'district_id', 'nom', 'distance_totale', 'temps_estime_min', 'frequence', 'saison', 'statut',
        'point_depart', 'depart_lat', 'depart_lon',
        'temps_aller_min', 'temps_retour_min', 'temps_depot_min', 'temps_coaching_min', 'version', 'urgence',
    ];

    protected function casts(): array
    {
        return ['distance_totale' => 'decimal:2', 'depart_lat' => 'float', 'depart_lon' => 'float', 'urgence' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Le temps estimé suit la décomposition (aller + retour + dépôt + coaching) dès qu'une composante est saisie.
        static::saving(function (Circuit $c) {
            $c->version ??= 'existant';   // champ facultatif dans les formulaires
            $parts = array_filter([$c->temps_aller_min, $c->temps_retour_min, $c->temps_depot_min, $c->temps_coaching_min], fn ($v) => $v !== null && $v !== '');
            if ($parts) {
                $c->temps_estime_min = (int) array_sum($parts);
            }
        });
    }

    /** Tournée de plus de 8 heures. */
    public function depasseDureeMax(): bool
    {
        return (int) $this->temps_estime_min > self::DUREE_MAX_MIN;
    }

    /** Saison (« seche » ou « pluvieuse ») d'une date : mois secs réglés par LOGIMASTER_DRY_SEASON_MONTHS. */
    public static function saisonDe(\DateTimeInterface|string $date): string
    {
        $month = (int) \Carbon\Carbon::parse($date)->format('n');

        return in_array($month, (array) config('logimaster.dry_season_months'), true) ? 'seche' : 'pluvieuse';
    }

    /** Circuits utilisables à une date : ceux de la saison correspondante et ceux valables toute l'année. */
    public function scopeValablesLe(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereIn('saison', ['toute_annee', self::saisonDe($date)]);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    /** Étapes dans l'ordre de passage ; distance_km = distance depuis l'étape précédente. */
    public function espc()
    {
        return $this->belongsToMany(Espc::class, 'circuit_espc')->withPivot(['ordre', 'distance_km', 'livre_via_espc_id'])->orderBy('circuit_espc.ordre');
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
                'ordre' => $i + 1, 'nom' => $e->nom, 'type' => $e->type, 'via' => $e->pivot->livre_via_espc_id ? $this->espc->firstWhere('id', $e->pivot->livre_via_espc_id)?->nom : null, 'distance' => $declared, 'estimee' => $estimated,
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
