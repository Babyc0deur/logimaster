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
        // limite par IP et par compte (e-mail pour le web et l'API, identifiant pour l'application mobile)
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.mb_strtolower((string) ($request->input('email') ?? $request->input('identifiant')))));

        // règle unique des mots de passe choisis par les utilisateurs : 12 caractères, lettres et chiffres ; en production,
        // refus des mots de passe connus comme compromis (service Have I Been Pwned, k-anonymat)
        \Illuminate\Validation\Rules\Password::defaults(fn () => $this->app->isProduction()
            ? \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers()->uncompromised()
            : \Illuminate\Validation\Rules\Password::min(12)->letters()->numbers());

        \App\Support\AdminAudit::register();
    }
}
