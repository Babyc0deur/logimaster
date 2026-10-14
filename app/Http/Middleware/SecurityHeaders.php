<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP sur toutes les réponses (OWASP A02) : la politique de contenu n'autorise que les hôtes
 * réellement utilisés (polices, Leaflet, tuiles OpenStreetMap, calcul d'itinéraire), interdit l'affichage du site
 * dans un cadre d'un autre domaine et force HTTPS en production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(self), camera=(self), microphone=(), payment=(), usb=()',
            'Content-Security-Policy' => self::csp(),
        ];
        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    public static function csp(): string
    {
        // Filament, Livewire et Alpine utilisent des scripts et styles en ligne et l'évaluation d'expressions :
        // 'unsafe-inline' et 'unsafe-eval' restent nécessaires ; la politique limite surtout les origines externes.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://unpkg.com",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://unpkg.com https://ui-avatars.com",
            "connect-src 'self' https://router.project-osrm.org",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
