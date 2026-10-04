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