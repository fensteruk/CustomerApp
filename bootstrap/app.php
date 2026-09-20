<?php

use App\Http\Middleware\EnsureActivePortalAccount;
use App\Http\Middleware\EnsureActiveSiteIsAssigned;
use App\Http\Middleware\PreventAuthenticatedResponseCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [PreventAuthenticatedResponseCaching::class]);

        $middleware->alias([
            'active.portal' => EnsureActivePortalAccount::class,
            'active.site' => EnsureActiveSiteIsAssigned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('portal/office/*') && $request->expectsJson()),
        );
    })->create();
