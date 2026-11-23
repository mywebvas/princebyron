<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (review P1-9)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of proxy IPs or CIDR ranges whose forwarding headers
    | may be trusted. Empty means "trust nobody", which is correct for direct
    | hosting: a request arriving straight from the internet can set its own
    | X-Forwarded-For, and honouring it would let a client forge its IP address
    | in logs, rate-limit keys and fraud review.
    |
    | Set this only to infrastructure you control — Cloudflare's ranges, or your
    | host's load balancer. `*` trusts every proxy and is intended only for a
    | managed edge that rewrites the header itself.
    |
    | This lives in a config file rather than being read with env() inside
    | bootstrap/app.php because `php artisan config:cache` — which a production
    | host should always run — stops env() from returning anything outside
    | config files. A bootstrap-time env() call would silently become null the
    | moment the config was cached, disabling proxy trust for the deployment that
    | needed it most.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Security Headers (review P1-10)
    |--------------------------------------------------------------------------
    |
    | The Content-Security-Policy is built from these fragments so an operator
    | can extend it (for example to allow a payment iframe or an analytics host)
    | without editing application code.
    |
    */

    'headers' => [

        // Extra sources appended to the storefront CSP, per directive. Anything
        // added here widens the policy, so it is deliberately done by hostname
        // and never by wildcard.
        'csp_extra' => [
            'script-src' => [],
            'style-src'  => [],
            'img-src'    => [],
            'font-src'   => [],
            'media-src'  => [],
            'connect-src' => [],
            'frame-src'  => [],
        ],

        // HSTS max-age in seconds. Sent only over HTTPS.
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    ],

];
