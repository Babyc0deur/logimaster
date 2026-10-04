<?php

$dir = __DIR__ . '/app/Models';

$models = [
    'Ravitaillement' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ravitaillement extends Model {
    use HasUuids, SoftDeletes;
    protected $table = 'ravitaillements';
    protected $fillable = ['owner_type', 'owner_id', 'district_id', 'vehicle_id', 'sortie_id', 'driver_id', 'litres', 'prix_unitaire', 'station', 'numero_facture', 'km_compteur', 'version'];
    protected function casts(): array { return ['litres' => 'decimal:2', 'prix_unitaire' => 'decimal:2']; }
    public function getMontantTotalAttribute(): float { return (float) $this->litres * (float) $this->prix_unitaire; }
    public function district() { return $this->belongsTo(District::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function sortie() { return $this->belongsTo(SortieVehicule::class, 'sortie_id'); }
}
PHP,

    'Immobilisation' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immobilisation extends Model {
    use HasUuids, SoftDeletes;
    protected $table = 'immobilisations';
    protected $fillable = ['owner_type', 'owner_id', 'district_id', 'vehicle_id', 'date_debut', 'date_fin', 'motif', 'description', 'montant', 'prestataire', 'statut', 'version'];
    protected function casts(): array { return ['date_debut' => 'date', 'date_fin' => 'date']; }
    public function district() { return $this->belongsTo(District::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}
PHP,

    'Vidange' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Vidange extends Model {
    use HasUuids;
    protected $table = 'vidanges';
    protected $fillable = ['vehicle_id', 'district_id', 'date', 'km', 'type', 'montant', 'prestataire', 'numero_facture', 'prochain_km'];
    protected function casts(): array { return ['date' => 'date']; }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function district() { return $this->belongsTo(District::class); }
}
PHP,

    'Circuit' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Circuit extends Model {
    use HasUuids;
    protected $fillable = ['district_id', 'nom', 'distance_totale', 'temps_estime_min', 'frequence', 'statut'];
    public function district() { return $this->belongsTo(District::class); }
    public function espc() { return $this->belongsToMany(Espc::class, 'circuit_espc')->withPivot('ordre')->orderBy('circuit_espc.ordre'); }
    public function sorties() { return $this->hasMany(SortieVehicule::class); }
}
PHP,

    'Espc' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Espc extends Model {
    use HasUuids;
    protected $table = 'espc';
    protected $fillable = ['district_id', 'nom', 'type', 'gps_lat', 'gps_lon', 'responsable', 'telephone', 'statut'];
    public function district() { return $this->belongsTo(District::class); }
    public function circuits() { return $this->belongsToMany(Circuit::class, 'circuit_espc')->withPivot('ordre'); }
}
PHP,

    'Document' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Document extends Model {
    use HasUuids;
    protected $fillable = ['documentable_type', 'documentable_id', 'district_id', 'categorie', 'fichier_url', 'date_expiration', 'uploaded_by'];
    protected function casts(): array { return ['date_expiration' => 'date']; }
    public function documentable() { return $this->morphTo(); }
    public function district() { return $this->belongsTo(District::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
PHP,

    'Expense' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model {
    use HasUuids;
    protected $fillable = ['district_id', 'sortie_id', 'type', 'beneficiaire', 'montant', 'commentaire'];
    public function district() { return $this->belongsTo(District::class); }
    public function sortie() { return $this->belongsTo(SortieVehicule::class, 'sortie_id'); }
}
PHP,

    'Budget' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model {
    use HasUuids;
    protected $fillable = ['district_id', 'period', 'montant_alloue'];
    protected function casts(): array { return ['period' => 'date']; }
    public function district() { return $this->belongsTo(District::class); }
}
PHP,

    'DistrictDevice' => <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DistrictDevice extends Model {
    use HasUuids;
    protected $table = 'district_devices';
    protected $fillable = ['district_id', 'device_name', 'first_seen_at', 'last_sync_at'];
    protected function casts(): array { return ['first_seen_at' => 'datetime', 'last_sync_at' => 'datetime']; }
    public function district() { return $this->belongsTo(District::class); }
}
PHP,
];

foreach ($models as $name => $content) {
    file_put_contents("$dir/$name.php", $content);
}

// Nettoyage des anciens fichiers groupés
@unlink("$dir/FleetOperations.php");
@unlink("$dir/FleetSupport.php");

echo "Tous les modèles ont été séparés avec succès.";
