<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Abonnement Web Push d'un téléphone (un utilisateur peut en avoir plusieurs). */
class PushSubscription extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'user_agent', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
