<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Staff invitations — multi-owner staff onboarding
|--------------------------------------------------------------------------
|
| Endpoints (all owner-only, rate-limited):
|   GET    /admin/staff/invitations            — list pending invitations
|   POST   /admin/staff/invitations            — create an invitation
|   DELETE /admin/staff/invitations/{id}       — revoke an invitation
|   GET    /admin/staff/invitations/{token}/accept — public accept page
|   POST   /admin/staff/invitations/{token}/accept — accept and create account
|
| Invitation tokens are 256 bits of entropy and single-use.
*/

class StaffInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireOwner();

        $invitations = StaffInvitation::with('inviter:name,email')
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
            });

        return response()->json([
            'success'     => true,
            'invitations' => $invitations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireOwner();

        // Rate limit: max 10 invitations per minute per owner.
        if (RateLimiter::tooManyAttempts("staff-invite:{$request->user()->id}", 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many invitations. Please wait before sending more.',
            ], 429);
        }

        $validated = $request->validate([
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'level'      => ['required', 'in:owner,manager,staff,viewer'],
            'permissions'=> ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        // Revoke any existing unaccepted invitation for this email.
        StaffInvitation::where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->delete();

        RateLimiter::hit("staff-invite:{$request->user()->id}");

        $invitation = StaffInvitation::create([
            'email'      => $validated['email'],
            'level'      => $validated['level'],
            'permissions'=> $validated['permissions'] ?? null,
            'invited_by' => $request->user()->id,
        ]);

        AuditEvent::create([
            'actor_id'       => $request->user()->id,
            'action'         => 'staff.invitation.created',
            'auditable_type' => StaffInvitation::class,
            'auditable_id'   => $invitation->id,
            'new_values'     => [
                'email'       => $invitation->email,
                'level'       => $invitation->level,
                'invited_by'  => $request->user()->email,
            ],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return response()->json([
            'success'   => true,
            'invitation' => [
                'id'          => $invitation->id,
                'email'       => $invitation->email,
                'level'       => $invitation->level,
                'token'       => $invitation->token,
                'expires_at'  => $invitation->expires_at,
                'accept_url'  => url("/admin/staff/invitations/{$invitation->token}/accept"),
            ],
        ], 201);
    }

    public function destroy(Request $request, StaffInvitation $invitation): JsonResponse
    {
        $this->requireOwner();

        if ($invitation->isAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke an accepted invitation.',
            ], 422);
        }

        $invitation->delete();

        AuditEvent::create([
            'actor_id'       => $request->user()->id,
            'action'         => 'staff.invitation.revoked',
            'auditable_type' => StaffInvitation::class,
            'auditable_id'   => $invitation->id,
            'old_values'     => [
                'email' => $invitation->email,
                'level' => $invitation->level,
            ],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked.',
        ]);
    }

    public function showAccept(string $token)
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isClaimable()) {
            return view('errors.404', [
                'message' => 'This invitation link is invalid, expired, or has already been used.',
            ]);
        }

        return view('admin.staff.accept', compact('invitation'));
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = StaffInvitation::where('token', $token)->first();

        if (! $invitation || ! $invitation->isClaimable()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation link is invalid, expired, or has already been used.',
            ], 404);
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($invitation, $validated) {
            $staffData = $invitation->accept();

            User::create([
                'name'              => $validated['name'],
                'email'             => $invitation->email,
                'password'          => Hash::make($validated['password']),
                'role'              => User::ROLE_STAFF,
                'staff_level'       => $staffData['level'],
                'permissions'       => $staffData['permissions'],
                'invited_by'        => $invitation->invited_by,
                'invited_at'        => now(),
                'email_verified_at' => now(), // Invitation flow is the verification.
            ]);
        });

        AuditEvent::create([
            'actor_id'       => null,
            'action'         => 'staff.invitation.accepted',
            'auditable_type' => StaffInvitation::class,
            'auditable_id'   => $invitation->id,
            'new_values'     => [
                'email' => $invitation->email,
                'level' => $invitation->level,
            ],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Account created. You can now sign in to the admin panel.',
            'redirect' => url('/admin/login'),
        ]);
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
            Log::warning('Unauthorized admin staff access attempt', [
                'user_id' => $user?->id,
                'email'   => $user?->email,
                'ip'      => request()->ip(),
            ]);
            abort(403);
        }
    }
}
