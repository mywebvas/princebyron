<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Exports — admin panel navigation page
|--------------------------------------------------------------------------
|
| Download buttons for CSV exports: orders, users, products, songs.
| Owner-only — enforced server-side in mount() and in the controller.
*/

class Exports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Staff Management';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.exports';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isOwner() || ! $user->is_active) {
            Log::warning('Unauthorized exports page access', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'ip'      => request()->ip(),
            ]);
            abort(403);
        }
    }

    public function export(string $type): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isOwner() || ! $user->is_active) {
            abort(403);
        }

        $validTypes = ['orders', 'users', 'products', 'songs'];

        if (! in_array($type, $validTypes, true)) {
            Notification::make()
                ->title('Invalid export type')
                ->danger()
                ->send();

            return;
        }

        \App\Models\AuditEvent::create([
            'actor_id'       => $user->id,
            'action'         => 'admin.export.requested',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'new_values'     => ['export_type' => $type, 'requested_by' => $user->email],
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);

        // The browser will follow the link to the export route directly.
        // This method is kept for audit logging; the actual download is
        // triggered by the view's anchor tag.
    }
}
