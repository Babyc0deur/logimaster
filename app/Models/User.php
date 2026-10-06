<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    public const ROLE_PRES_ADMIN = 'pres_admin';
    public const ROLE_REGION_MANAGER = 'region_manager';
    public const ROLE_DISTRICT_MANAGER = 'district_manager';
    public const ROLE_SUPERVISEUR = 'superviseur_bailleur';

    /** Chef de mission ou passager qui exécute les circuits depuis l'application mobile. */
    public const ROLE_CONVOYEUR = 'convoyeur';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'two_factor_enabled',
        'region_id',
        'personnel_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'last_mobile_login_at' => 'datetime',
        ];
    }

    public function districts()
    {
        return $this->belongsToMany(District::class, 'district_user');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /** Fiche personnel (chef de mission ou passager) du convoyeur. */
    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** Compte réservé à l'application mobile : aucun accès à l'interface d'administration. */
    public function isConvoyeurOnly(): bool
    {
        return $this->hasRole(self::ROLE_CONVOYEUR) && $this->roles->count() === 1;
    }

    public function isNational(): bool
    {
        return $this->hasRole(self::ROLE_PRES_ADMIN);
    }

    /**
     * Identifiants des districts accessibles ; null = tous (accès national).
     *
     * @return array<int, string>|null
     */
    public function accessibleDistrictIds(): ?array
    {
        if ($this->isNational()) {
            return null;
        }

        // Districts rattachés individuellement + tous les districts de la région du responsable régional.
        $ids = $this->districts()->pluck('districts.id');
        if ($this->region_id) {
            $ids = $ids->merge(District::where('region_id', $this->region_id)->pluck('id'));
        }

        return $ids->unique()->values()->all();
    }

    public function canAccessDistrict(string $districtId): bool
    {
        $ids = $this->accessibleDistrictIds();

        return $ids === null || in_array($districtId, $ids, true);
    }

    /** District ouvert après la connexion : celui de la configuration (MEAGUI) s'il est accessible, sinon le premier de la liste. */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        $name = config('logimaster.default_district');
        $preferred = $name ? District::where('name', $name)->first() : null;

        return $preferred && $this->canAccessDistrict($preferred->getKey()) ? $preferred : $this->getTenants($panel)->first();
    }

    public function getTenants(Panel $panel): Collection
    {
        $ids = $this->accessibleDistrictIds();

        return District::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->canAccessDistrict($tenant->getKey());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active && ! $this->isConvoyeurOnly();
    }
}
