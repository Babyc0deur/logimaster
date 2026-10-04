<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes de l'application mobile : jeton émis par la connexion mobile, compte actif rattaché à une fiche personnel,
 * et (sauf pour « password » et « logout ») mot de passe provisoire déjà remplacé.
 * Usage : mobile.access (toutes les routes) ou mobile.access:allow-temporary-password.
 */
class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->personnel_id || ! $user->can('execute_circuits') || ! $user->tokenCan('mobile')) {
            return response()->json(['message' => 'Accès à l\'application mobile refusé.'], 403);
        }
        if ($mode !== 'allow-temporary-password' && $user->must_change_password) {
            return response()->json(['message' => 'Changez votre mot de passe provisoire avant de continuer.', 'code' => 'password_change_required'], 423);
        }

        return $next($request);
    }
}
