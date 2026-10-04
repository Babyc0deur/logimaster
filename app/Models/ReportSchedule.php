<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Envoi récurrent d'un rapport par email (mensuel : le jour N du mois pour le mois écoulé ; hebdomadaire : le jour N de la semaine). */
class ReportSchedule extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'type', 'formats', 'scope', 'frequence', 'jour', 'destinataires', 'actif', 'dernier_envoi_at'];

    /** Valeurs par défaut également côté objet (pas seulement en base). */
    protected $attributes = ['actif' => true, 'frequence' => 'mensuelle', 'jour' => 1];

    protected function casts(): array
    {
        return ['formats' => 'array', 'scope' => 'array', 'destinataires' => 'array', 'actif' => 'boolean', 'dernier_envoi_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Doit-on envoyer aujourd'hui ? (une seule fois par jour) */
    public function isDue(?\DateTimeInterface $today = null): bool
    {
        $today = \Carbon\CarbonImmutable::instance($today ?? now())->startOfDay();
        if (! $this->actif || ($this->dernier_envoi_at && $this->dernier_envoi_at->isSameDay($today))) {
            return false;
        }

        return $this->frequence === 'hebdomadaire'
            ? $today->isoWeekday() === (int) $this->jour
            : $today->day === min((int) $this->jour, $today->daysInMonth);
    }
}
