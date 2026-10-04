<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ravitaillement extends Model {
    use HasUuids, SoftDeletes, \App\Models\Concerns\DefaultsOwnerToDistrict;
    protected $table = 'ravitaillements';
    protected $fillable = ['owner_type', 'owner_id', 'district_id', 'vehicle_id', 'sortie_id', 'driver_id', 'litres', 'prix_unitaire', 'station', 'numero_facture', 'km_compteur', 'version', 'facture_path', 'valide_at', 'valide_par', 'anomalie', 'date_ravitaillement', 'motif', 'client_ref', 'saisi_par'];
    protected function casts(): array { return ['litres' => 'decimal:2', 'prix_unitaire' => 'decimal:2', 'valide_at' => 'datetime', 'date_ravitaillement' => 'date:Y-m-d']; }
    public function getMontantTotalAttribute(): float { return (float) $this->litres * (float) $this->prix_unitaire; }
    protected static function booted(): void
    {
        // Détection d'anomalies à l'enregistrement (compteur incohérent, surconsommation).
        static::creating(fn (Ravitaillement $r) => $r->date_ravitaillement ??= today());
        static::saving(function (Ravitaillement $r) {
            $r->anomalie = app(\App\Domain\Fuel\FuelAnalyzer::class)->detectAnomaly($r);
        });
    }

    public function district() { return $this->belongsTo(District::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function sortie() { return $this->belongsTo(SortieVehicule::class, 'sortie_id'); }
}