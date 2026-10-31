<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    use HasUuids;

    protected $table = 'personnels';

    protected $fillable = [
        'district_id', 'nom_complet', 'fonction', 'telephone', 'statut', 'email',
        // chauffeur : informations de conduite (recopiées sur la fiche chauffeur liée)
        'matricule', 'categorie_permis', 'numero_permis', 'permis_expiration', 'date_obtention_permis', 'vehicule_principal_id',
    ];

    protected function casts(): array
    {
        return ['permis_expiration' => 'date', 'date_obtention_permis' => 'date'];
    }

    protected static function booted(): void
    {
        static::saved(function (Personnel $personnel) {
            \App\Domain\Personnel\DriverLink::fromPersonnel($personnel);   // chauffeur : sa fiche chauffeur suit la personne
        });
    }

    public const FONCTIONS = ['chauffeur' => 'Chauffeur', 'chef_mission' => 'Chef de mission', 'passager' => 'Passager', 'autre' => 'Autre'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    /** Fiche chauffeur liée (sorties conduites, chronogramme, alertes de permis). */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehiculePrincipal()
    {
        return $this->belongsTo(Vehicle::class, 'vehicule_principal_id');
    }

    /** Sorties conduites (chauffeur). */
    public function sortiesConduites()
    {
        return $this->hasMany(SortieVehicule::class, 'driver_id', 'driver_id');
    }

    public function sortiesEnTantQueChef()
    {
        return $this->hasMany(SortieVehicule::class, 'chef_mission_id');
    }

    public function sortiesEnTantQuePassager()
    {
        return $this->belongsToMany(SortieVehicule::class, 'sortie_personnel', 'personnel_id', 'sortie_id');
    }
}
