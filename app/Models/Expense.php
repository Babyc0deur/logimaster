<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasUuids;

    /** Types de frais ; « carburant » et « maintenance » existent pour la saisie mais sont comptés avec leurs propres postes (pas de double comptage). */
    public const TYPES = [
        'collation' => 'Collation', 'chargement' => 'Frais de chargement', 'dechargement' => 'Frais de déchargement', 'hebergement' => 'Hébergement',
        'peage' => 'Péage', 'enlevement_espc' => 'Enlèvement ESPC', 'carburant' => 'Carburant', 'maintenance' => 'Maintenance', 'autre' => 'Autres dépenses',
    ];

    protected $fillable = ['district_id', 'sortie_id', 'vehicle_id', 'espc_id', 'type', 'beneficiaire', 'montant', 'commentaire', 'date_depense', 'motif', 'client_ref', 'saisi_par'];

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

    /** Centre de santé bénéficiaire (enlèvement au district par l'ESPC). */
    public function espc()
    {
        return $this->belongsTo(Espc::class);
    }
}
