<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Livraison prévue d'un site (ESPC) dans une sortie planifiée du chronogramme : ordre de passage,
 * statut (planifié / livré / non livré), date et lieu réels de livraison, raison en cas d'échec.
 * Sert à la fois de table pivot (Chronogramme::espc()) et de modèle autonome (suivi des livraisons).
 */
class LivraisonEspc extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'livraisons_espc';

    protected $keyType = 'string';

    protected $guarded = [];

    public const STATUTS = ['planifie' => 'Planifiée', 'livre' => 'Livrée', 'non_livre' => 'Non livrée'];

    public const LIEUX = ['site' => 'Sur site', 'transit' => 'Point de transit'];

    protected function casts(): array
    {
        return ['date_livraison' => 'date:Y-m-d', 'ordre' => 'integer', 'saisi_at' => 'datetime', 'colis' => 'integer', 'gps_precision_m' => 'integer', 'gps_ecart_m' => 'integer', 'lat' => 'float', 'lon' => 'float'];
    }

    public function chronogramme()
    {
        return $this->belongsTo(Chronogramme::class);
    }

    public function espc()
    {
        return $this->belongsTo(Espc::class);
    }

    public function saisiPar()
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    /** Jours de retard par rapport à la date prévue (0 si dans les délais ou non livrée). */
    public function getRetardJoursAttribute(): int
    {
        if ($this->statut !== 'livre' || ! $this->date_livraison || ! $this->chronogramme) {
            return 0;
        }

        return max(0, (int) $this->chronogramme->date_prevue->startOfDay()->diffInDays($this->date_livraison->startOfDay(), false));
    }

    /** « dans_les_delais » | « retard_24h » (≤ 1 jour) | « retard_plus_24h » | null si non livrée. */
    public function getDelaiAttribute(): ?string
    {
        if ($this->statut !== 'livre') {
            return null;
        }

        return match (true) {
            $this->retard_jours === 0 => 'dans_les_delais',
            $this->retard_jours <= 1 => 'retard_24h',
            default => 'retard_plus_24h',
        };
    }
}
