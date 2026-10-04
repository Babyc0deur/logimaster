<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasUuids;

    protected $fillable = ['district_id', 'sortie_id', 'vehicle_id', 'type', 'beneficiaire', 'montant', 'commentaire', 'date_depense', 'motif'];

    protected function casts(): array
    {
        return ['date_depense' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Expense $e) => $e->date_depense ??= today());
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function sortie()
    {
        return $this->belongsTo(SortieVehicule::class, 'sortie_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
