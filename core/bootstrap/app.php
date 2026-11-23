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
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // P1-10: security headers travel with the application, not only with
        // one web server's config file.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // P1-9: trusted proxies.
        //
        // Behind Cloudflare, a cPanel LiteSpeed proxy or any TLS-terminating
        // load balancer, request()->ip() is the proxy's address. Every throttle
        // key then collapses onto one IP, so a single abuser locks out every
        // customer — and the audit log records the wrong actor address.
        //
        // The list is configurable rather than hardcoded, because trusting a
        // proxy range you do not control lets a client forge its own IP. An
        // empty list (the default) is correct for direct hosting and safe.
        $proxies = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));

        if (! empty($proxies)) {
            // '*' is a deliberate opt-in for platforms that rewrite the header
            // themselves (e.g. a managed edge) — never a default.
            $middleware->trustProxies(
                at: $proxies === ['*'] ? '*' : $proxies,
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
