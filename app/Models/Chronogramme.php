<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Chronogramme extends Model
{
    use HasUuids;

    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'realisee' => 'Réalisée',
        'reportee' => 'Reportée',
        'annulee' => 'Annulée',
    ];

    public const VALIDATIONS = [
        'brouillon' => 'Brouillon',
        'soumis' => 'Soumis à validation',
        'valide' => 'Validé',
        'refuse' => 'Refusé',
    ];

    /** Champs de planification : figés une fois le planning validé par le superviseur. */
    public const CHAMPS_VERROUILLES = ['date_prevue', 'heure_depart', 'circuit_id', 'vehicle_id', 'driver_id', 'motif'];

    protected $fillable = [
        'district_id', 'vehicle_id', 'driver_id', 'circuit_id', 'date_prevue', 'heure_depart',
        'motif', 'destination', 'statut', 'sortie_id', 'commentaires', 'raison',
        'validation_statut', 'soumis_at', 'valide_par', 'valide_at', 'motif_refus', 'date_initiale',
    ];

    protected function casts(): array
    {
        return [
            'date_prevue' => 'date:Y-m-d', 'date_initiale' => 'date:Y-m-d',
            'soumis_at' => 'datetime', 'valide_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Une sortie planifiée sur un circuit reprend la liste ordonnée de ses sites (modifiable ensuite).
        static::created(function (Chronogramme $plan) {
            if ($plan->circuit_id && $plan->espc()->doesntExist()) {
                $sync = Circuit::find($plan->circuit_id)?->espc->mapWithKeys(fn ($e) => [$e->id => ['ordre' => $e->pivot->ordre]])->all();
                $sync && $plan->espc()->sync($sync);
            }
        });

        static::updating(function (Chronogramme $plan) {
            $core = array_intersect(self::CHAMPS_VERROUILLES, array_keys($plan->getDirty()));
            if (! $core) {
                return;
            }
            if ($plan->getOriginal('validation_statut') === 'valide') {
                throw ValidationException::withMessages(['validation_statut' => 'Planning validé par le superviseur : modification impossible (demandez la levée de la validation).']);
            }
            // Toute modification d'un planning soumis le renvoie en brouillon : il doit être re-soumis.
            if ($plan->getOriginal('validation_statut') === 'soumis' && ! $plan->isDirty('validation_statut')) {
                $plan->validation_statut = 'brouillon';
                $plan->soumis_at = null;
            }
            if ($plan->isDirty('date_prevue') && ! $plan->date_initiale && $plan->getOriginal('date_prevue')) {
                $plan->date_initiale = $plan->getOriginal('date_prevue');
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->validation_statut === 'valide';
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function circuit()
    {
        return $this->belongsTo(Circuit::class);
    }

    /** Sites (ESPC) à desservir, dans l'ordre de passage, avec leur suivi de livraison (pivot). */
    public function espc()
    {
        return $this->belongsToMany(Espc::class, 'livraisons_espc')
            ->using(LivraisonEspc::class)
            ->withPivot(['id', 'ordre', 'statut', 'date_livraison', 'lieu_livraison', 'raison_non_livraison', 'commentaire'])
            ->withTimestamps()
            ->orderBy('livraisons_espc.ordre');
    }

    /** Équipe prévue (chef de mission, passagers) : voit la sortie dans l'application mobile une fois le planning validé. */
    public function personnels()
    {
        return $this->belongsToMany(Personnel::class, 'chronogramme_personnel');
    }

    public function livraisons()
    {
        return $this->hasMany(LivraisonEspc::class)->orderBy('ordre');
    }

    public function sortie()
    {
        return $this->belongsTo(SortieVehicule::class, 'sortie_id');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** Planifiée dont la date est dépassée. */
    public function getEstEnRetardAttribute(): bool
    {
        return $this->statut === 'planifiee' && $this->date_prevue->isBefore(today());
    }

    /** Marque comme livrés (sur site, à la date de la sortie) les sites dont la livraison n'a pas encore été renseignée. */
    public function marquerSitesLivres(?string $date = null): int
    {
        return $this->livraisons()->where('statut', 'planifie')->update([
            'statut' => 'livre', 'date_livraison' => $date ?? $this->date_prevue->toDateString(), 'lieu_livraison' => 'site', 'updated_at' => now(),
        ]);
    }

    /**
     * Démarre la sortie prévue : crée la sortie (km départ = km actuel du véhicule), la relie au
     * planning et marque l'entrée « réalisée ». Sans effet si elle est déjà réalisée ou annulée.
     */
    public function demarrer(): SortieVehicule
    {
        abort_if(in_array($this->statut, ['realisee', 'annulee'], true), 422, 'Cette sortie ne peut plus être démarrée.');
        abort_if($this->vehicle_id === null, 422, 'Affectez un véhicule avant de démarrer la sortie.');

        return DB::transaction(function () {
            $sortie = SortieVehicule::create([
                'owner_type' => 'district',
                'owner_id' => $this->district_id,
                'district_id' => $this->district_id,
                'vehicle_id' => $this->vehicle_id,
                'driver_id' => $this->driver_id,
                'circuit_id' => $this->circuit_id,
                'km_depart' => $this->vehicle->km_actuel ?? 0,
                'motif' => $this->motif ?? 'distribution',
                'destination' => $this->destination,
                'statut' => 'en_cours',
            ]);
            $this->update(['statut' => 'realisee', 'sortie_id' => $sortie->id]);

            return $sortie;
        });
    }
}
