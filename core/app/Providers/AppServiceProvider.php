<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters (review P1-8).
     *
     * Before this, only login/register/booking were throttled. Everything that
     * costs the platform something was unbounded:
     *
     *   - /api/cart/*          — trivially hammered; each add hits the database.
     *   - /api/checkout/process— the worst one. Every call can create an order
     *                            row, reserve stock and write audit events.
     *   - /downloads/{id}      — sequential ids made entitlement discovery cheap.
     *   - /webhooks/*          — unauthenticated by nature, so signature
     *                            verification was the only barrier to a brute
     *                            force attempt.
     *
     * Limits are deliberately generous enough for a real customer (nobody adds
     * 60 items a minute by hand) and tight enough that scripted abuse gets
     * nowhere. All responses are 429 with the app's normal error surface.
     */
    protected function configureRateLimiters(): void
    {
        // General browsing of cart state. Cheap, but per-IP.
        RateLimiter::for('cart', function (Request $request) {
            return Limit::perMinute(60)->by($this->throttleKey($request));
        });

        // Mutations to the cart. Tighter than reads: each one writes the session
        // and may touch the database for stock.
        RateLimiter::for('cart-mutation', function (Request $request) {
            return Limit::perMinute(30)->by($this->throttleKey($request));
        });

        // Checkout is the expensive one. 10 attempts per minute is far more than
        // a person needs and far less than an order-spam script wants.
        RateLimiter::for('checkout', function (Request $request) {
            return [
                Limit::perMinute(10)->by($this->throttleKey($request)),
                // Global ceiling so a distributed burst cannot exhaust stock or
                // the database connection pool.
                Limit::perMinute(120)->by('checkout-global'),
            ];
        });

        // Downloads: per-user, because a signed-in customer re-downloading a
        // purchased master is legitimate and frequent-ish, while probing ids is
        // not. Keyed by user where possible so one user cannot lock out others.
        RateLimiter::for('downloads', function (Request $request) {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(20)->by($key);
        });

        // Webhooks: providers legitimately retry, so allow headroom, but not
        // unbounded signature attempts. Keyed by provider + IP.
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by('webhook:'.$request->path().':'.$request->ip());
        });

        // Account actions.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($this->throttleKey($request));
        });

        // Order detail lookup: authenticated only, but rate-limited to prevent
        // rapid enumeration of order references even with the ownership check.
        RateLimiter::for('order', function (Request $request) {
            return Limit::perMinute(30)->by($this->throttleKey($request));
        });
    }

    /**
     * Throttle key: user id when authenticated, otherwise normalised email + IP.
     *
     * `Str::transliterate` is used because an email can contain unicode that
     * would make two visually identical keys different cache entries.
     */
    protected function throttleKey(Request $request): string
    {
        if ($request->user()) {
            return 'user:'.$request->user()->id;
        }

        $email = (string) $request->input('email');

        return $email !== ''
            ? Str::transliterate(Str::lower($email)).'|'.$request->ip()
            : 'ip:'.$request->ip();
    }
}
