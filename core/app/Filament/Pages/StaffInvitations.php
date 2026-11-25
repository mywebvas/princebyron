<?php

namespace App\Filament\Pages;

use App\Models\StaffInvitation;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Staff Invitations — admin panel navigation page
|--------------------------------------------------------------------------
|
| Lists pending staff invitations and lets the owner revoke them.
| Owner-only — enforced server-side in mount() and in the controller.
*/

class StaffInvitations extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationGroup = 'Staff Management';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.staff-invitations';

    public array $invitations = [];

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->isOwner() || ! $user->is_active) {
            Log::warning('Unauthorized staff invitations page access', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'ip'      => request()->ip(),
            ]);
            abort(403);
        }

        $this->invitations = StaffInvitation::with('inviter:name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id'          => $invitation->id,
                    'email'       => $invitation->email,
                    'level'       => $invitation->level,
                    'permissions' => $invitation->permissions,
                    'invited_by'  => $invitation->inviter?->email,
                    'created_at'  => $invitation->created_at,
                    'expires_at'  => $invitation->expires_at,
                    'accepted_at' => $invitation->accepted_at,
                    'is_claimable'=> $invitation->isClaimable(),
                    'is_expired'  => $invitation->isExpired(),
                    'is_accepted' => $invitation->isAccepted(),
                ];
            })->toArray();
    }
}
