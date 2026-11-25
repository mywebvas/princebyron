<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Providers\Filament\AdminPanelProvider;

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

        // P2-3: give every server-side failure a short quotable reference and
        // put that same reference in the log. Without it, a 500 page is a dead
        // end for the customer and an unsolvable ticket for support.
        //
        // Only 5xx gets a reference: a 404 is not an incident, and generating
        // tokens for bot traffic would be noise in both the log and the page.
        $exceptions->report(function (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() < 500) {
                return;
            }

            if (app()->bound('request')) {
                \Illuminate\Support\Facades\Log::error('Unhandled request failure', [
                    'reference' => app(\App\Support\ErrorReference::class)->current(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'url' => request()->fullUrl(),
                ]);
            }
        });
    })

    // Register the owner-only admin panel (Filament 5).
    // Discovered resources: Orders, Products, Songs, BookingRequests,
    // ContentSections, Settings, ShippingRates, AuditEvents.
    // Access is gated by User::canAccessPanel() (owner + active only).
    ->withProviders([
        AdminPanelProvider::class,
    ])
    ->create();
