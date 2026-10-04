<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Filament\Models\Contracts\HasAvatar;

class Pres extends Model
{
    use HasUuids;

    protected $table = 'pres';
    protected $fillable = ['name'];

    public function regions()
    {
        return $this->hasMany(Region::class);
    }
}
