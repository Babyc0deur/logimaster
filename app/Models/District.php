<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Filament\Models\Contracts\HasCurrentTenantLabel;

class District extends Model implements HasCurrentTenantLabel
{
    use HasUuids;

    protected $fillable = [
        'region_id',
        'name',
        'sync_id',
        'sync_password_hash',
        'sync_password_rotated_at',
    ];

    protected $hidden = ['sync_password_hash'];

    protected function casts(): array
    {
        return [
            'sync_password_rotated_at' => 'datetime',
        ];
    }

    public function getCurrentTenantLabel(): string
    {
        return $this->name;
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'district_user');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class);
    }

    public function circuits()
    {
        return $this->hasMany(Circuit::class);
    }

    public function espcs()
    {
        return $this->hasMany(Espc::class);
    }

    public function sorties()
    {
        return $this->hasMany(SortieVehicule::class);
    }

    public function ravitaillements()
    {
        return $this->hasMany(Ravitaillement::class);
    }

    public function immobilisations()
    {
        return $this->hasMany(Immobilisation::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function devices()
    {
        return $this->hasMany(DistrictDevice::class);
    }
}
