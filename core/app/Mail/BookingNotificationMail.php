<?php

namespace App\Mail;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Ministry Booking Request — {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: <<<HTML
            <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #181310; line-height: 1.6;">
                <h2 style="color: #9E472A;">New Ministry Inquiry Received</h2>
                <p>A new ministry booking inquiry has been submitted and is awaiting owner review in the backoffice.</p>
                <div style="background: #F8F4ED; padding: 16px; border-radius: 8px; margin: 20px 0;">
                    <p><strong>Reference:</strong> {$this->booking->reference}</p>
                    <p><strong>Contact:</strong> {$this->booking->contact_name} ({$this->booking->contact_email})</p>
                    <p><strong>Phone:</strong> {$this->booking->contact_phone}</p>
                    <p><strong>Organisation:</strong> {$this->booking->organisation}</p>
                    <p><strong>Event Name:</strong> {$this->booking->event_name}</p>
                    <p><strong>Date:</strong> {$this->booking->event_date}</p>
                    <p><strong>Location:</strong> {$this->booking->event_city}, {$this->booking->event_country}</p>
                    <p><strong>Expected Audience:</strong> {$this->booking->expected_audience}</p>
                    <p><strong>Description:</strong> {$this->booking->event_description}</p>
                </div>
                <p><a href="/admin/booking-requests" style="display: inline-block; background: #DFA759; color: #0E0B09; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Review in Backoffice</a></p>
            </div>
            HTML,
        );
    }
}
