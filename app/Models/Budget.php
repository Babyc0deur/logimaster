<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model {
    use HasUuids;
        public const POSTES = ['global' => 'Global', 'carburant' => 'Carburant', 'maintenance' => 'Maintenance', 'autres' => 'Autres frais'];
    protected $fillable = ['district_id', 'period', 'montant_alloue', 'poste', 'bailleur'];
    protected function casts(): array { return ['period' => 'date:Y-m-d']; }
    public function district() { return $this->belongsTo(District::class); }
}