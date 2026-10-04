<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immobilisation extends Model {
    use HasUuids, SoftDeletes, \App\Models\Concerns\DefaultsOwnerToDistrict;
    protected $table = 'immobilisations';
    protected $fillable = ['owner_type', 'owner_id', 'district_id', 'vehicle_id', 'date_debut', 'date_fin', 'motif', 'description', 'montant', 'prestataire', 'statut', 'version', 'numero_facture', 'facture_path'];
    protected function casts(): array { return ['date_debut' => 'date', 'date_fin' => 'date']; }
    public const MOTIFS = [
        'panne' => 'Panne mécanique',
        'accident' => 'Accident',
        'carrosserie' => 'Réparation carrosserie',
        'controle_technique' => 'Contrôle technique',
        'visite_technique' => 'Visite technique',
        'reparation' => 'Réparation',
        'depannage' => 'Dépannage',
        'vidange' => 'Vidange',
        'incident' => 'Incidents (vol, incendie, accident…)',
        'autre' => 'Autre',
    ];

    public const STATUTS = [
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'en_attente_pieces' => 'En attente pièces',
    ];

    /** Durée en jours (inclusive) ; sans date de fin, jusqu'à aujourd'hui. */
    public function getDureeJoursAttribute(): int
    {
        $fin = $this->date_fin ?? today();

        return max(1, (int) $this->date_debut->startOfDay()->diffInDays($fin->startOfDay()) + 1);
    }

    public function district() { return $this->belongsTo(District::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
}