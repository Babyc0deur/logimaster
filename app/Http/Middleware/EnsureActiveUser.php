<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        // un jeton de l'application mobile ne sert qu'à l'application mobile : jamais à l'API complète, même pour un administrateur
        if ($user->currentAccessToken()?->abilities === ['mobile']) {
            return response()->json(['message' => "Ce jeton est réservé à l'application mobile."], 403);
        }

        return $next($request);
    }
}
