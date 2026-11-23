<?php

namespace App\Http\Controllers;

use App\Mail\BookingAcknowledgementMail;
use App\Mail\BookingNotificationMail;
use App\Models\BookingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_name'      => 'required|string|max:255',
            'contact_email'     => 'required|email|max:255',
            'contact_phone'     => 'nullable|string|max:50',
            'organisation'      => 'nullable|string|max:255',
            'event_type'        => 'nullable|string|max:100',
            'event_name'        => 'required|string|max:255',
            'event_date'        => 'nullable|date',
            'event_city'        => 'nullable|string|max:100',
            'event_state'       => 'nullable|string|max:100',
            'event_country'     => 'nullable|string|max:2',
            'expected_audience' => 'nullable|integer|min:1',
            'event_description' => 'required|string|max:3000',
            'requirements'      => 'nullable|string|max:2000',
        ]);

        $reference = 'PB-BK-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $booking = BookingRequest::create(array_merge($validated, [
            'reference' => $reference,
            'status'    => BookingRequest::STATUS_NEW,
        ]));

        // Dispatch local Mailpit email acknowledgement (safe dev environment)
        try {
            Mail::to($booking->contact_email)->send(new BookingAcknowledgementMail($booking));

            $ownerEmail = Setting::get('contact_email', config('mail.from.address', 'owner@princebyron.local'));
            if ($ownerEmail) {
                Mail::to($ownerEmail)->send(new BookingNotificationMail($booking));
            }
        } catch (\Throwable $e) {
            Log::warning("Mail delivery failed for booking {$reference}: " . $e->getMessage());
        }

        return response()->json([
            'success'   => true,
            'reference' => $booking->reference,
            'message'   => 'Thank you. Your booking inquiry has been received. Our ministry will follow up with you shortly.',
        ], 201);
    }
}
