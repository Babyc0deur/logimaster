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