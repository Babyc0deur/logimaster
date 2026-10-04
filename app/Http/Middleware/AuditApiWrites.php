<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Journalise les écritures (POST/PUT/PATCH/DELETE) réussies de l'API dans audit_logs. */
class AuditApiWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->isSuccessful() && $request->user()) {
            AuditLog::create([
                'user_id' => $request->user()->getKey(),
                'action' => $request->method().' '.$request->route()?->uri(),
                'module' => explode('/', trim(str_replace('api/', '', $request->route()?->uri() ?? ''), '/'))[0] ?: null,
                'metadata' => [
                    'status' => $response->getStatusCode(),
                    'params' => $request->route()?->parameters(),
                    'ip' => $request->ip(),
                ],
            ]);
        }

        return $response;
    }
}
