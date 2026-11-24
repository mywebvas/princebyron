<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Prince Byron Official Platform
|--------------------------------------------------------------------------
|
| Dedicated routes for public pages, customer catalog, customer auth,
| server-authoritative commerce, webhooks, and backoffice demo.
| All prices, permissions, and order states are determined server-side.
|
*/

// Public Editorial & Discovery Pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/music', [PageController::class, 'music'])->name('music');
Route::get('/music/download-free', [PageController::class, 'downloadFreePreview'])->name('music.download-free');
Route::get('/store', [PageController::class, 'store'])->name('store');
Route::get('/store/{slug}', [PageController::class, 'product'])->name('product');
Route::get('/product/{slug}', [PageController::class, 'product']);
Route::get('/cart', [PageController::class, 'cart'])->name('cart');
Route::get('/checkout', [PageController::class, 'checkout'])->name('checkout');
Route::get('/booking', [PageController::class, 'booking'])->name('booking');

// Customer Authentication
Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:auth');
Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

// Customer Account & Fan Vault
Route::get('/account', [CustomerAccountController::class, 'dashboard'])->name('account');
Route::get('/account/orders/{reference}', [CustomerAccountController::class, 'orderDetail'])->middleware('auth')->name('account.order');

// Server-Authoritative Cart Endpoints
// Throttled per P1-8: reads are generous, mutations tighter, because each
// mutation writes the session and may query stock.
Route::middleware('throttle:cart')->group(function () {
    Route::get('/api/cart', [CartController::class, 'data'])->name('cart.data');
    Route::get('/api/checkout/calculate', [CheckoutController::class, 'calculate'])->name('checkout.calculate');
});

Route::middleware('throttle:cart-mutation')->group(function () {
    Route::post('/api/cart/items', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/api/cart/items', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/api/cart/items', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/api/cart', [CartController::class, 'clear'])->name('cart.clear');
});

// Checkout Endpoints (Recalculated Server-Side)
// The most expensive route in the application: creates an order, reserves stock
// and writes audit events. Previously unthrottled.
Route::post('/api/checkout/process', [CheckoutController::class, 'process'])
    ->middleware('throttle:checkout')
    ->name('checkout.process');

// Payment Provider Webhooks (Sandbox Mode)
// Throttled: unauthenticated by nature, so signature verification is the only
// barrier to a brute-force attempt (P1-8).
Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])->name('webhooks.stripe');
    Route::post('/webhooks/paypal', [WebhookController::class, 'handlePayPal'])->name('webhooks.paypal');
});

// Protected Digital Downloads (Entitlement-Authorized Only)
Route::get('/downloads/{id}', [DownloadController::class, 'download'])
    ->middleware(['auth', 'throttle:downloads'])
    ->name('downloads.file');

// Ministry Booking Inquiry Submission
Route::post('/booking', [BookingController::class, 'submit'])->middleware('throttle:10,1')->name('booking.submit');

// Public Simulated Demo Admin Area (Planned Filament 5 Theme Preview)
Route::get('/admin/preview', [PageController::class, 'adminPreview'])->name('admin.preview');

/*
|--------------------------------------------------------------------------
| Health check — minimal safe public response; zero diagnostic leakage.
|--------------------------------------------------------------------------
*/
Route::get('/up', function () {
    $allOk = true;

    // Database connectivity check
    try {
        DB::connection()->getPdo();
    } catch (\Throwable) {
        $allOk = false;
    }

    /*
     | Cache connectivity — read-only (review P3-9).
     |
     | This used to do Cache::put() followed by Cache::get() on EVERY probe. On
     | the `file` driver the installer leaves behind, that is a disk write per
     | request; a monitoring service polling every 30s writes 2,880 files a day
     | into storage/framework/cache, and on a shared host with an inode quota that
     | is a self-inflicted outage.
     |
     | A cache whose store is unreachable throws on read just as reliably as it
     | does on write, so the write proved nothing the read does not. The key is
     | seeded once by the deploy (see the readiness page); a miss is not a
     | failure — an unwritable or unreachable store is.
     */
    try {
        Cache::get('health_ping');
    } catch (\Throwable) {
        $allOk = false;
    }

    if (! $allOk) {
        return response()->json(['status' => 'error'], 503);
    }

    return response()->json(['status' => 'ok'], 200);
});

/*
|--------------------------------------------------------------------------
| Sitemap (review P2-4)
|--------------------------------------------------------------------------
| Generated from the live catalogue rather than a static file, so a new release
| or product appears without anybody remembering to edit an XML file — which is
| the reason hand-maintained sitemaps are always wrong after a month.
|
| Unpurchasable sample items are excluded: they must never be indexed, and the
| product page marks them noindex anyway.
*/
Route::get('/sitemap.xml', function (\App\Services\CatalogService $catalog) {
    $urls = [
        ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => url('/music'), 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['loc' => url('/store'), 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['loc' => url('/about'), 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['loc' => url('/booking'), 'changefreq' => 'monthly', 'priority' => '0.6'],
    ];

    foreach ($catalog->songs() as $song) {
        // Only published, purchasable tracks; the catalogue already filters
        // unpublished rows, so this is about the sample fallback items.
        if (($song['purchasable'] ?? true) === false) {
            continue;
        }

        $urls[] = [
            'loc' => url('/music'),
            'changefreq' => 'monthly',
            'priority' => '0.5',
        ];
    }

    foreach ($catalog->products() as $product) {
        if (($product['purchasable'] ?? false) === false) {
            continue;
        }

        $urls[] = [
            'loc' => url('/store/'.$product['slug']),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach ($urls as $entry) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>'.e($entry['loc'])."</loc>\n";
        $xml .= '    <changefreq>'.$entry['changefreq']."</changefreq>\n";
        $xml .= '    <priority>'.$entry['priority']."</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');
