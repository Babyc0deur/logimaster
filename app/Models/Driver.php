<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'district_id', 'matricule', 'nom_complet', 'telephone',
        'categorie_permis', 'permis_expiration', 'statut', 'version',
        'email', 'numero_permis', 'date_obtention_permis', 'vehicule_principal_id',
    ];

    public const STATUTS = ['actif' => 'Actif', 'conge' => 'En congé', 'absent' => 'Absent', 'suspendu' => 'Suspendu', 'inactif' => 'Inactif'];

    protected function casts(): array
    {
        return [
            'permis_expiration' => 'date',
            'date_obtention_permis' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Driver $driver) => \App\Domain\Personnel\DriverLink::fromDriver($driver));
    }

    /** Personne du personnel correspondant à ce chauffeur. */
    public function personnel()
    {
        return $this->hasOne(Personnel::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function vehiculePrincipal()
    {
        return $this->belongsTo(Vehicle::class, 'vehicule_principal_id');
    }

    public function sorties()
    {
        return $this->hasMany(SortieVehicule::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function permisExpire(): bool
    {
        return $this->permis_expiration && $this->permis_expiration->diffInDays(now(), false) >= -30;
    }
}
