<?php

namespace App\Mail;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingAcknowledgementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking Request Received — {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: <<<HTML
            <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #181310; line-height: 1.6;">
                <h2 style="color: #9E472A;">Thank You for Your Ministry Inquiry</h2>
                <p>Dear {$this->booking->contact_name},</p>
                <p>We have safely received your ministry booking request for Prince Byron. Our ministry team will review your event details and follow up with availability and quotation details.</p>
                <div style="background: #F8F4ED; padding: 16px; border-radius: 8px; margin: 20px 0;">
                    <p><strong>Booking Reference:</strong> {$this->booking->reference}</p>
                    <p><strong>Event:</strong> {$this->booking->event_name}</p>
                    <p><strong>Organisation:</strong> {$this->booking->organisation}</p>
                    <p><strong>Date:</strong> {$this->booking->event_date}</p>
                </div>
                <p>Blessings,<br>Prince Byron Ministry Team</p>
            </div>
            HTML,
        );
    }
}
