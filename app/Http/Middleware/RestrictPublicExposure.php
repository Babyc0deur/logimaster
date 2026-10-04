<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode « tunnel » (LOGIMASTER_TUNNEL_MODE=true) : l'application est exposée sur Internet par une adresse publique pour que les
 * convoyeurs installent la PWA. Depuis cette adresse, seuls la page d'accueil, l'application mobile et son API sont joignables ;
 * l'administration et le reste de l'API restent réservés au réseau local (localhost, 192.168.x.x, 10.x.x.x…).
 */
class RestrictPublicExposure
{
    /** Chemins joignables depuis une adresse publique. */
    private const PUBLIC = ['/', 'm', 'm/*', 'pwa/*', 'api/mobile/*', 'up', 'favicon.ico', 'robots.txt'];

    public function handle(Request $request, Closure $next): Response
    {
        if (static::restricted($request) && ! $request->is(self::PUBLIC)) {
            abort(404);
        }

        return $next($request);
    }

    /** Vrai si le mode tunnel est actif et que la requête arrive par une adresse publique. */
    public static function restricted(Request $request): bool
    {
        return (bool) config('logimaster.tunnel_mode') && ! self::isLocalHost($request->getHost());
    }

    public static function isLocalHost(string $host): bool
    {
        return $host === 'localhost' || str_ends_with($host, '.localhost') || (bool) preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|::1$|\[::1\]$)/', $host);
    }
}
