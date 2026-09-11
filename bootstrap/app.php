<?php

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
        /*
         * The room publish endpoint is called by the local packet builder, not
         * by a browser: there is no session and therefore no CSRF token to
         * present. Its authentication is the shared secret the controller
         * checks with hash_equals, the same shape Eat Well Planner's pin
         * drop-box uses.
         */
        $middleware->validateCsrfTokens(except: [
            'api/internal/room',
            // Called by the local builder with a shared secret, not a browser
            // session, so there is no CSRF token to send.
            'api/internal/pin-asset',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
