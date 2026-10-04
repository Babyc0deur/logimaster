<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // whereDateBetween('date_sortie', $from, $to) : bornes incluses, indépendant du format de stockage (date ou datetime).
        \Illuminate\Database\Eloquent\Builder::macro('whereDateBetween', function (string $column, $from, $to) {
            $fmt = fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d;

            return $this->whereDate($column, '>=', $fmt($from))->whereDate($column, '<=', $fmt($to));
        });

        // documents.documentable_type stocke 'vehicle' | 'driver' | 'sortie' (cf. spécification).
        Relation::morphMap([
            'vehicle' => Vehicle::class,
            'driver' => Driver::class,
            'sortie' => SortieVehicule::class,
        ]);

        // Les politiques App\Policies\{Modèle}Policy sont découvertes automatiquement ; le modèle Role de Spatie est hors App\Models.
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.$request->input('email')));
    }
}
