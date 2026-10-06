<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    use HasUuids;

    protected $table = 'personnels';

    protected $fillable = [
        'district_id', 'nom_complet', 'fonction', 'telephone', 'statut', 'email', 'identifiant', 'code_acces',
        // chauffeur : informations de conduite (recopiées sur la fiche chauffeur liée)
        'matricule', 'categorie_permis', 'numero_permis', 'permis_expiration', 'date_obtention_permis', 'vehicule_principal_id',
    ];

    protected $hidden = ['code_acces'];

    protected function casts(): array
    {
        return ['code_acces' => 'encrypted', 'permis_expiration' => 'date', 'date_obtention_permis' => 'date'];
    }

    /** Chef de mission ou passager actif = convoyeur d'office : le compte mobile suit la fiche (création, désactivation, réactivation). */
    protected static function booted(): void
    {
        static::saved(function (Personnel $personnel) {
            \App\Domain\Personnel\DriverLink::fromPersonnel($personnel);   // chauffeur : sa fiche chauffeur suit la personne
            \App\Domain\Mobile\ConvoyeurAccess::enabled() && app(\App\Domain\Mobile\ConvoyeurAccess::class)->sync($personnel);
        });

        // Fiche supprimée : son accès mobile (compte, appareils, notifications) disparaît avec elle.
        static::deleting(function (Personnel $personnel) {
            if ($user = $personnel->user()->first()) {
                $user->tokens()->delete();
                $user->pushSubscriptions()->delete();
                $user->delete();
            }
        });
    }

    public const FONCTIONS = ['chauffeur' => 'Chauffeur', 'chef_mission' => 'Chef de mission', 'passager' => 'Passager', 'autre' => 'Autre'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    /** Compte mobile de cette personne (s'il existe). */
    public function user()
    {
        return $this->hasOne(User::class);
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
