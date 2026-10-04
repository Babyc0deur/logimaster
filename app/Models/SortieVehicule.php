<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SortieVehicule extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sorties_vehicules';

    protected $fillable = [
        'owner_type', 'owner_id', 'district_id', 'vehicle_id', 'driver_id',
        'circuit_id', 'km_depart', 'km_arrivee', 'motif', 'destination',
        'circuit_respecte', 'commentaires', 'statut', 'version',
        'date_sortie', 'chef_mission_id', 'point_depart', 'point_arrivee', 'etapes', 'validated_at', 'validated_by',
    ];

    protected function casts(): array
    {
        return [
            'circuit_respecte' => 'boolean',
            'etapes' => 'array',
            'date_sortie' => 'date:Y-m-d',
            'validated_at' => 'datetime',
        ];
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

    public function ravitaillements()
    {
        return $this->hasMany(Ravitaillement::class, 'sortie_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'sortie_id');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Km parcourus calculé
    public function getKmParcourus(): ?int
    {
        if ($this->km_arrivee && $this->km_depart) {
            return $this->km_arrivee - $this->km_depart;
        }
        return null;
    }

    public const STATUTS = [
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'validee' => 'Validée',
        'annulee' => 'Annulée',
    ];

    public const MOTIFS = [
        'distribution' => 'Livraison ESPC',
        'redistribution' => 'Redistribution',
        'enlevement_npsp' => 'Enlèvement NPSP',
        'supervision' => 'Supervision',
        'coaching' => 'Coaching',
        'autre' => 'Autres',
    ];

    public function chefMission()
    {
        return $this->belongsTo(Personnel::class, 'chef_mission_id');
    }

    public function passagers()
    {
        return $this->belongsToMany(Personnel::class, 'sortie_personnel', 'sortie_id', 'personnel_id');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** Distance parcourue (km), null tant que la sortie n'est pas clôturée. */
    public function getDistanceAttribute(): ?int
    {
        return $this->km_arrivee !== null ? $this->km_arrivee - $this->km_depart : null;
    }

    public function getCoutCarburantAttribute(): float
    {
        return (float) $this->ravitaillements()->sum(\DB::raw('litres * prix_unitaire'));
    }

    public function getCoutAutresFraisAttribute(): float
    {
        return (float) $this->expenses()->sum('montant');
    }

    public function getCoutTotalAttribute(): float
    {
        return $this->cout_carburant + $this->cout_autres_frais;
    }

    /** Consommation observée (L/100 km) sur cette sortie, null si non calculable. */
    public function getConsommationReelleAttribute(): ?float
    {
        $distance = $this->distance;
        $litres = (float) $this->ravitaillements()->sum('litres');

        return $distance > 0 && $litres > 0 ? round($litres / $distance * 100, 2) : null;
    }

    /** Écart (%) entre la consommation observée et la consommation théorique du véhicule. */
    public function getEcartConsommationAttribute(): ?float
    {
        $theorique = (float) (Vehicle::whereKey($this->vehicle_id)->value('consommation_theorique') ?? 0);
        $reelle = $this->consommation_reelle;

        return $theorique > 0 && $reelle !== null ? round(($reelle - $theorique) / $theorique * 100, 1) : null;
    }

    // Mettre à jour le km du véhicule à la clôture
    /** Clôture depuis l'application mobile : les sites non traités par le convoyeur restent à traiter (pas de livraison automatique). */
    public bool $skipSiteDelivery = false;

    protected static function booted(): void
    {
        // owner_* est obligatoire en base : par défaut une sortie saisie au bureau appartient au district.
        static::creating(function (SortieVehicule $sortie) {
            $sortie->owner_type ??= 'district';
            $sortie->owner_id ??= $sortie->district_id;
            $sortie->date_sortie ??= today();
        });

        // Saisir le kilométrage d'arrivée clôture la sortie en cours.
        static::saving(function (SortieVehicule $sortie) {
            if ($sortie->km_arrivee !== null && $sortie->statut === 'en_cours') {
                $sortie->statut = 'terminee';
            }
        });

        static::updated(function (SortieVehicule $sortie) {
            // Sortie terminée : les sites de la sortie planifiée correspondante sont livrés (sur site) sauf indication contraire.
            if (! $sortie->skipSiteDelivery && $sortie->wasChanged('statut') && in_array($sortie->statut, ['terminee', 'validee'], true)) {
                Chronogramme::where('sortie_id', $sortie->id)->get()->each->marquerSitesLivres($sortie->date_sortie?->toDateString());
            }

            if ($sortie->isDirty('km_arrivee') && $sortie->km_arrivee) {
                $sortie->vehicle->update(['km_actuel' => $sortie->km_arrivee]);
            }
        });
    }
}
