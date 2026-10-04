<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Espc extends Model
{
    use HasUuids;

    protected $table = 'espc';

    protected $fillable = ['district_id', 'nom', 'type', 'gps_lat', 'gps_lon', 'responsable', 'telephone', 'statut', 'adresse', 'email'];

    protected function casts(): array
    {
        return ['gps_lat' => 'float', 'gps_lon' => 'float'];
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function circuits()
    {
        return $this->belongsToMany(Circuit::class, 'circuit_espc')->withPivot(['ordre', 'distance_km']);
    }

    public function livraisons()
    {
        return $this->hasMany(LivraisonEspc::class);
    }

    /**
     * Historique des livraisons du site (sorties planifiées échues, non annulées) : livrées, selon planning, retards, taux de respect.
     *
     * @return array{planifiees: int, livrees: int, selon_planning: int, retards: int, non_livrees: int, taux: ?float, derniere: ?string}
     */
    public function livraisonStats(): array
    {
        $rows = $this->livraisons()->with('chronogramme:id,date_prevue,statut')->get()
            ->filter(fn (LivraisonEspc $l) => $l->chronogramme && $l->chronogramme->statut !== 'annulee' && $l->chronogramme->date_prevue->lte(today()));
        $planned = $rows->count();
        $onSchedule = $rows->filter(fn ($l) => \App\Domain\Indicators\Deliveries::onSchedule($l, $l->chronogramme))->count();

        return [
            'planifiees' => $planned,
            'livrees' => $rows->where('statut', 'livre')->count(),
            'selon_planning' => $onSchedule,
            'retards' => $rows->filter(fn ($l) => $l->statut === 'livre' && $l->retard_jours > 0)->count(),
            'non_livrees' => $rows->where('statut', '!=', 'livre')->count(),
            'taux' => $planned > 0 ? $onSchedule / $planned : null,
            'derniere' => $rows->where('statut', 'livre')->max('date_livraison')?->toDateString(),
        ];
    }

    public function hasGps(): bool
    {
        return $this->gps_lat !== null && $this->gps_lon !== null;
    }
}
