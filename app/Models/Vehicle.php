<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasUuids, SoftDeletes;

    public const APPARTENANCES = [
        'district' => 'District',
        'particulier' => 'Particulier',
        'location' => 'Location',
        'mutualisation' => 'Mutualisation',
    ];

    protected $fillable = [
        'district_id', 'immatriculation', 'marque', 'modele',
        'type_vehicule', 'type_carburant', 'consommation_theorique',
        'km_actuel', 'km_vidange', 'date_ct', 'date_assurance', 'statut', 'version',
        'annee_circulation', 'poids_vide', 'capacite_charge', 'volume_utile', 'appartenance',
        'bailleur', 'date_reception', 'prix_carburant', 'date_dernier_releve',
        'vignette_annee', 'date_dernier_ct', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_ct' => 'date',
            'date_assurance' => 'date',
            'date_reception' => 'date',
            'date_dernier_releve' => 'date',
            'date_dernier_ct' => 'date',
            'prix_carburant' => 'decimal:2',
            'consommation_theorique' => 'decimal:2',
        ];
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function sorties()
    {
        return $this->hasMany(SortieVehicule::class);
    }

    public function ravitaillements()
    {
        return $this->hasMany(Ravitaillement::class);
    }

    public function immobilisations()
    {
        return $this->hasMany(Immobilisation::class);
    }

    public function vidanges()
    {
        return $this->hasMany(Vidange::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // Accesseur : km parcourus total
    public function getKmParcourus(): int
    {
        return $this->sorties()
            ->whereNotNull('km_arrivee')
            ->selectRaw('SUM(km_arrivee - km_depart) as total')
            ->value('total') ?? 0;
    }

    // Alerte: vidange à venir dans 500 km
    public function vidangeImminente(): bool
    {
        return $this->km_vidange && ($this->km_vidange - $this->km_actuel) <= 500;
    }

    // Alerte: CT expiré ou dans 30 jours
    public function ctExpiré(): bool
    {
        return $this->date_ct && $this->date_ct->diffInDays(now(), false) >= -30;
    }
}
