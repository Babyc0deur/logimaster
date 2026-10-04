<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Vidange extends Model {
    use HasUuids;
    protected $table = 'vidanges';
    protected $fillable = ['vehicle_id', 'district_id', 'date', 'km', 'type', 'montant', 'prestataire', 'numero_facture', 'prochain_km', 'prochaine_date', 'observations', 'facture_path'];
    protected function casts(): array { return ['date' => 'date', 'prochaine_date' => 'date']; }
    protected static function booted(): void
    {
        // Une vidange reporte l'échéance du véhicule et fait avancer son kilométrage connu.
        static::saved(function (Vidange $v) {
            $vehicle = Vehicle::find($v->vehicle_id);
            if (! $vehicle) {
                return;
            }
            $updates = [];
            if ($v->prochain_km) {
                $updates['km_vidange'] = $v->prochain_km;
            }
            if ($v->km > $vehicle->km_actuel) {
                $updates['km_actuel'] = $v->km;
            }
            $updates && $vehicle->update($updates);
        });
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function district() { return $this->belongsTo(District::class); }
}