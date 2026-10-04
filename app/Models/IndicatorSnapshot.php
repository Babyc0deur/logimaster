<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorSnapshot extends Model
{
    protected $fillable = ['district_id', 'indicator_key', 'period', 'value', 'breakdown', 'computed_at'];

    protected function casts(): array
    {
        return [
            'period' => 'date:Y-m-d',
            'value' => 'float',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
