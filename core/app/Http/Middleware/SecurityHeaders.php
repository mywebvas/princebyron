<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — application-level security headers.
 *
 * WHY THIS EXISTS (review P1-10)
 * ------------------------------
 * Every security header lived in `.htaccess`. That protects Apache and nothing
 * else: an nginx or LiteSpeed host that ignores `.htaccess`, or a cPanel setup
 * where a different vhost wins, would serve the whole storefront with no CSP,
 * no HSTS and no frame protection. Headers that live only in a server config
 * file are not part of the application — they are part of one deployment.
 *
 * DESIGN NOTES
 * ------------
 * - The storefront has no inline <script> blocks and no inline event handlers
 *   (verified: every script is a bundled asset), so script-src can be plain
 *   'self' with no nonce and no 'unsafe-inline'. That is the strict case, and it
 *   is only possible because the front end was built properly — do not weaken it
 *   without checking what actually needs the relaxation.
 * - 'unsafe-eval' is required by Alpine's expression evaluator and is scoped to
 *   script-src. Documented rather than hidden so the trade-off is visible.
 * - style-src allows 'unsafe-inline' because the templates use style attributes
 *   for per-element art direction (gradients, aspect ratios).
 * - HSTS is sent only over HTTPS: over plain HTTP every browser ignores it and
 *   the header just misleads the operator.
 * - Filament's panel gets a relaxed policy (it ships its own inline styles and
 *   script bootstrap) but the same transport and framing protections.
 * - Nothing here overwrites a header the web server already set, so `.htaccess`
 *   and this middleware cannot fight each other.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never decorate streamed downloads or binary responses: headers on a
        // file body are harmless but pointless, and CSP on a download can make
        // some browsers refuse to save it.
        if ($this->shouldSkip($request, $response)) {
            return $response;
        }

        $panel = $this->isAdminPanel($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            // Filament renders in an iframe for some of its own UI (modals in
            // embedded contexts); SAMEORIGIN is safe for both surfaces.
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Content-Security-Policy' => $panel ? $this->panelPolicy() : $this->storefrontPolicy(),
        ];

        if ($request->isSecure()) {
            // 1 year, include subdomains. No preload: that is a hard-to-reverse
            // commitment the owner must make deliberately, not a default an
            // installer chooses for them.
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Detect the Filament panel by route name, not by a config key.
     *
     * Filament v3 registers routes as `filament.<panel>.*`; there is no
     * `filament.path` config value to read (that was v2), so a config lookup
     * would silently return the default and every admin page would get the
     * storefront policy — which would break the panel's own inline script. The
     * path check is kept as a cheap fallback for requests with no route yet.
     */
    private function isAdminPanel(Request $request): bool
    {
        if ($request->routeIs('filament.*')) {
            return true;
        }

        return $request->is('admin') || $request->is('admin/*');
    }

    private function shouldSkip(Request $request, Response $response): bool
    {
        if ($response->headers->has('Content-Disposition')) {
            return true;
        }

        return $request->is('downloads/*');
    }

    /** Storefront: strict, because the front end needs nothing relaxed. */
    private function storefrontPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "media-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    /**
     * Filament's panel injects its own bootstrap script and Alpine styles, so it
     * needs the inline allowance. It is still locked to same-origin and cannot
     * be framed by another site.
     */
    private function panelPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
