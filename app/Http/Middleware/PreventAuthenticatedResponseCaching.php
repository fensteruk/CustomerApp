<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventAuthenticatedResponseCaching
{
    /**
     * Prevent authenticated response bodies from being stored or shared.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $wasAuthenticated = $request->user() !== null;
        $response = $next($request);

        if ($wasAuthenticated || $request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
