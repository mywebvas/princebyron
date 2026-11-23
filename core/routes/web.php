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
Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

// Customer Account & Fan Vault
Route::get('/account', [CustomerAccountController::class, 'dashboard'])->name('account');
Route::get('/account/orders/{reference}', [CustomerAccountController::class, 'orderDetail'])->middleware('auth')->name('account.order');

// Server-Authoritative Cart Endpoints
Route::get('/api/cart', [CartController::class, 'data'])->name('cart.data');
Route::post('/api/cart/items', [CartController::class, 'add'])->name('cart.add');
Route::patch('/api/cart/items', [CartController::class, 'update'])->name('cart.update');
Route::delete('/api/cart/items', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/api/cart', [CartController::class, 'clear'])->name('cart.clear');

// Checkout Endpoints (Recalculated Server-Side)
Route::get('/api/checkout/calculate', [CheckoutController::class, 'calculate'])->name('checkout.calculate');
Route::post('/api/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');

// Payment Provider Webhooks (Sandbox Mode)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [WebhookController::class, 'handlePayPal'])->name('webhooks.paypal');

// Protected Digital Downloads (Entitlement-Authorized Only)
Route::get('/downloads/{id}', [DownloadController::class, 'download'])->middleware('auth')->name('downloads.file');

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

    // Cache connectivity check
    try {
        Cache::put('health_ping', true, 5);
        if (Cache::get('health_ping') !== true) {
            $allOk = false;
        }
    } catch (\Throwable) {
        $allOk = false;
    }

    if (! $allOk) {
        return response()->json(['status' => 'error'], 503);
    }

    return response()->json(['status' => 'ok'], 200);
});
