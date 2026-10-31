<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Mise à disposition d'un véhicule d'un district (propriétaire) à un autre district (utilisateur) pendant une période.
 * Le propriétaire est le district du véhicule ; `district_id` est le district qui l'utilise.
 */
class VehicleMutualisation extends Model
{
    use HasUuids;

    protected $table = 'vehicle_mutualisations';

    public const ETATS = ['a_venir' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminée'];

    protected $fillable = ['vehicle_id', 'district_id', 'date_debut', 'date_fin', 'motif', 'statut', 'cree_par'];

    protected function casts(): array
    {
        return ['date_debut' => 'date:Y-m-d', 'date_fin' => 'date:Y-m-d'];
    }

    /** Le véhicule, même supprimé et même s'il appartient à un autre district que celui affiché (la mutualisation traverse les districts). */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withoutGlobalScopes();
    }

    /** District qui utilise le véhicule. */
    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** « a_venir », « en_cours » ou « terminee » (terminée à la main ou date de fin dépassée). */
    public function etat(?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();

        return match (true) {
            $this->statut === 'terminee', $this->date_fin !== null && $this->date_fin->lt($today) => 'terminee',
            $this->date_debut->gt($today) => 'a_venir',
            default => 'en_cours',
        };
    }

    /** Vrai si la mise à disposition couvre cette date (début et fin comprises ; terminée à la main : jusqu'à sa date de fin). */
    public function covers(CarbonImmutable|string $date): bool
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return $this->date_debut->lte($day) && ($this->date_fin === null || $this->date_fin->gte($day));
    }

    public function scopeForUser(Builder $query, array $districtIds): Builder
    {
        return $query->whereIn('district_id', $districtIds);
    }

    /**
     * Une autre mise à disposition du même véhicule chevauche-t-elle cette période ? (une période sans fin va jusqu'à l'infini)
     */
    public static function overlaps(string $vehicleId, string $debut, ?string $fin, ?string $ignoreId = null): bool
    {
        return static::query()->where('vehicle_id', $vehicleId)->where('statut', 'active')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereDate('date_debut', '<=', $fin ?: '9999-12-31')
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->exists();
    }
}
