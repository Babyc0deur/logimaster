<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Report extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'type', 'format', 'periode', 'scope', 'titre', 'fichier_path', 'statut'];

    protected function casts(): array
    {
        return ['scope' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exists_on_disk(): bool
    {
        return $this->fichier_path && Storage::disk('local')->exists($this->fichier_path);
    }
}
