<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasUuids;

    protected $fillable = ['pres_id', 'name'];

    public function pres()
    {
        return $this->belongsTo(Pres::class);
    }

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
