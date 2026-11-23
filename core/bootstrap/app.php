<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // P1-10: security headers travel with the application, not only with
        // one web server's config file.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // P1-9: trusted proxies.
        //
        // The list itself is read in App\Http\Middleware\TrustProxies at
        // request time. It MUST NOT be read here: this closure runs while the
        // container is still resolving the kernel, before the `config` binding
        // exists, so config() throws "Target class [config] does not exist" —
        // which broke artisan package:discover during `composer install` and
        // would break every request in production. See the middleware for the
        // full explanation.
        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\TrustProxies::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
