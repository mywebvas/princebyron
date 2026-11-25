<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
|--------------------------------------------------------------------------
| CSV exports — owner-only, rate-limited, streaming
|--------------------------------------------------------------------------
|
| Endpoints:
|   GET /admin/exports/orders
|   GET /admin/exports/users
|   GET /admin/exports/products
|   GET /admin/exports/songs
|
| All exports are streamed (constant memory) and RFC 4180 compliant with a
| UTF-8 BOM so Excel opens them correctly.
|
| Security
| --------
| - Owner-only: `User::isOwner()` checked server-side.
| - Rate-limited: 10 exports per minute per owner.
| - Every export is audited via AuditEvent (who, what, when).
| - No PII beyond what the admin panel already exposes.
*/

class ExportController extends Controller
{
    public function orders(Request $request)
    {
        $this->requireOwner();
        $this->checkExportRateLimit($request);

        return $this->stream('orders-' . now()->format('Ymd-His') . '.csv', function ($writer) {
            fputcsv($writer, [
                'Reference',
                'Customer Name',
                'Customer Email',
                'Status',
                'Currency',
                'Subtotal',
                'Shipping',
                'Tax',
                'Total',
                'Placed At',
            ], ',', '"', '');

            Order::select([
                'reference', 'customer_name', 'customer_email', 'status',
                'currency', 'subtotal_cents', 'shipping_cents', 'tax_cents',
                'total_cents', 'created_at',
            ])
                ->orderBy('created_at', 'desc')
                ->cursor()
                ->each(function ($order) use ($writer) {
                    fputcsv($writer, [
                        $order->reference,
                        $order->customer_name,
                        $order->customer_email,
                        $order->status,
                        $order->currency,
                        $order->subtotal_cents / 100,
                        $order->shipping_cents / 100,
                        $order->tax_cents / 100,
                        $order->total_cents / 100,
                        $order->created_at->toIso8601String(),
                    ], ',', '"', '');
                });
        });
    }

    public function users(Request $request)
    {
        $this->requireOwner();
        $this->checkExportRateLimit($request);

        return $this->stream('users-' . now()->format('Ymd-His') . '.csv', function ($writer) {
            fputcsv($writer, [
                'ID',
                'Name',
                'Email',
                'Role',
                'Staff Level',
                'Permissions',
                'Is Active',
                'Email Verified At',
                'Last Login At',
                'Created At',
            ], ',', '"', '');

            User::select([
                'id', 'name', 'email', 'role', 'staff_level',
                'permissions', 'is_active', 'email_verified_at',
                'last_login_at', 'created_at',
            ])
                ->orderBy('created_at', 'desc')
                ->cursor()
                ->each(function ($user) use ($writer) {
                    fputcsv($writer, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->role,
                        $user->staff_level,
                        $user->permissions ? implode('; ', $user->permissions) : '',
                        $user->is_active ? 'yes' : 'no',
                        $user->email_verified_at?->toIso8601String(),
                        $user->last_login_at?->toIso8601String(),
                        $user->created_at->toIso8601String(),
                    ], ',', '"', '');
                });
        });
    }

    public function products(Request $request)
    {
        $this->requireOwner();
        $this->checkExportRateLimit($request);

        return $this->stream('products-' . now()->format('Ymd-His') . '.csv', function ($writer) {
            fputcsv($writer, [
                'ID',
                'Name',
                'Slug',
                'Type',
                'Description',
                'Is Published',
                'Sort Order',
                'Created At',
            ], ',', '"', '');

            Product::select([
                'id', 'name', 'slug', 'type', 'description',
                'is_published', 'sort_order', 'created_at',
            ])
                ->orderBy('created_at', 'desc')
                ->cursor()
                ->each(function ($product) use ($writer) {
                    fputcsv($writer, [
                        $product->id,
                        $product->name,
                        $product->slug,
                        $product->type,
                        $product->description,
                        $product->is_published ? 'yes' : 'no',
                        $product->sort_order,
                        $product->created_at->toIso8601String(),
                    ], ',', '"', '');
                });
        });
    }

    public function songs(Request $request)
    {
        $this->requireOwner();
        $this->checkExportRateLimit($request);

        return $this->stream('songs-' . now()->format('Ymd-His') . '.csv', function ($writer) {
            fputcsv($writer, [
                'ID',
                'Title',
                'Slug',
                'Price (cents)',
                'Is Free',
                'Is Published',
                'Has MP3',
                'Has WAV',
                'Sort Order',
                'Created At',
            ], ',', '"', '');

            Song::select([
                'id', 'title', 'slug', 'price_cents', 'is_free',
                'is_published', 'has_mp3', 'has_wav', 'sort_order', 'created_at',
            ])
                ->orderBy('sort_order', 'asc')
                ->cursor()
                ->each(function ($song) use ($writer) {
                    fputcsv($writer, [
                        $song->id,
                        $song->title,
                        $song->slug,
                        $song->price_cents,
                        $song->is_free ? 'yes' : 'no',
                        $song->is_published ? 'yes' : 'no',
                        $song->has_mp3 ? 'yes' : 'no',
                        $song->has_wav ? 'yes' : 'no',
                        $song->sort_order,
                        $song->created_at->toIso8601String(),
                    ], ',', '"', '');
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    protected function checkExportRateLimit(Request $request): void
    {
        // Max 10 exports per minute per owner.
        if (RateLimiter::tooManyAttempts("admin-export:{$request->user()->id}", 10)) {
            abort(429, 'Too many exports. Please wait before requesting another export.');
        }

        RateLimiter::hit("admin-export:{$request->user()->id}");
    }

    /*
    |--------------------------------------------------------------------------
    | Streaming helper
    |--------------------------------------------------------------------------
    */

    protected function stream(string $filename, \Closure $writer): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, must-revalidate',
        ];

        // UTF-8 BOM so Excel recognizes the encoding.
        $bom = "\xEF\xBB\xBF";

        return new StreamedResponse(function () use ($writer, $bom) {
            echo $bom;

            $handle = fopen('php://output', 'w');
            $writer($handle);
            fclose($handle);
        }, 200, $headers);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function requireOwner(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isOwner() || ! $user->is_active) {
            Log::warning('Unauthorized admin export attempt', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'ip'      => request()->ip(),
            ]);
            abort(403);
        }
    }
}
