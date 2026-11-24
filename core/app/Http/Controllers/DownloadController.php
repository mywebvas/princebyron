<?php

namespace App\Http\Controllers;

use App\Models\DownloadEntitlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class DownloadController extends Controller
{
    /**
     * Authorized private audio delivery endpoint (SECURITY.md & ASVS 5.0 L2).
     */
    public function download(int $id, Request $request)
    {
        $user = Auth::user();

        $entitlement = DownloadEntitlement::with('song')->findOrFail($id);

        // 1. Strict cross-account ownership check
        if ($entitlement->user_id !== $user->id && ! $user->isOwner()) {
            abort(403, 'Unauthorized access to digital download entitlement.');
        }

        // 2. Active status check (e.g. not revoked by refund)
        if (! $entitlement->is_active) {
            abort(403, 'This download entitlement has been revoked or refunded.');
        }

        // 3. Expiration check
        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            abort(403, 'This download entitlement has expired.');
        }

        // 4. Check private storage for actual audio master file
        $format = $entitlement->format ?? 'mp3';
        if (! in_array($format, ['mp3', 'wav'], true)) {
            abort(403, 'Invalid download format.');
        }
        $slug = $entitlement->song->slug;
        $privateAudioPath = storage_path("app/private/audio/{$slug}.{$format}");

        if (File::exists($privateAudioPath)) {
            // Only consume download count and record timestamp when file is genuinely delivered
            $entitlement->increment('download_count');
            $entitlement->update(['last_downloaded_at' => now()]);

            $filename = "{$slug}-prince-byron.{$format}";
            return response()->download($privateAudioPath, $filename, [
                'Cache-Control'       => 'private, no-cache, no-store, must-revalidate',
                'Pragma'              => 'no-cache',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // 5. Safe demo / production pending state when audio masters have not yet been placed in private storage
        // Note: download_count is NOT consumed when file is unavailable
        return response()->json([
            'status'         => 'pending_masters',
            'message'        => 'Official audio masters for "' . $entitlement->song->title . '" are currently in studio production and awaiting final release. Your download entitlement is active and verified.',
            'song_title'     => $entitlement->song->title,
            'format'         => strtoupper($format),
            'download_count' => $entitlement->download_count,
            'entitlement_id' => $entitlement->id,
        ], 200);
    }
}
