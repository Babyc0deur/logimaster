<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Signalement terrain (application convoyeur) sur un véhicule : vidange réalisée ou panne / immobilisation, à valider par le bureau. */
class Signalement extends Model
{
    use HasUuids;

    protected $guarded = [];

    public const TYPES = ['vidange' => 'Vidange réalisée', 'panne' => 'Panne / immobilisation'];

    public const STATUTS = ['nouveau' => 'À traiter', 'valide' => 'Validé', 'rejete' => 'Rejeté'];

    protected function casts(): array
    {
        return ['details' => 'array', 'km' => 'integer', 'traite_at' => 'datetime', 'signale_at' => 'datetime'];
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function cible()
    {
        return $this->morphTo();
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return ($this->details ?? [])[$key] ?? $default;
    }
}
