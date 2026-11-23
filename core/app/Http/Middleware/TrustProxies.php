<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * TrustProxies — read the trusted-proxy list at request time, not at boot.
 *
 * WHY NOT `$middleware->trustProxies(at: config(...))` IN bootstrap/app.php
 * ------------------------------------------------------------------------
 * That was the first attempt and it broke every request: the middleware
 * configuration closure runs while the container is still resolving the kernel,
 * before the `config` binding exists, so calling config() there throws
 * "Target class [config] does not exist" — including inside `composer install`,
 * because the post-autoload-dump script runs `artisan package:discover`. A boot
 * failure of this kind is total: the site does not render, and the cause is
 * three stack frames away from the line that looks wrong.
 *
 * Reading it here is safe because proxies() is called from handle(), which runs
 * once per request in the full application context.
 *
 * WHY TRUST ANYTHING AT ALL (review P1-9)
 * ---------------------------------------
 * Behind Cloudflare, a cPanel LiteSpeed proxy or any TLS-terminating load
 * balancer, request()->ip() is the proxy's address. Every rate-limit key then
 * collapses onto one IP — so one abuser locks out every customer — and the audit
 * log records the wrong address for every action.
 *
 * The list defaults to empty, which is correct for direct hosting: a request
 * arriving from the internet can set its own X-Forwarded-For, and honouring it
 * would let a client forge its IP in logs and throttle keys.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $configured = config('security.trusted_proxies', []);

        if (is_string($configured)) {
            $configured = array_map('trim', explode(',', $configured));
        }

        return array_values(array_filter(
            (array) $configured,
            static fn ($proxy) => is_string($proxy) && $proxy !== ''
        ));
    }
}
