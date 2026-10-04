<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route d'un module optionnel : 404 tant que le module est retiré (ex. « module:finance »). */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Modules::enabled($module), 404, 'Module indisponible.');

        return $next($request);
    }
}
