<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationDeclinedMail extends QueuedMailable
{
    public function __construct(
        public Reservation $reservation,
        public ?string $reason = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reservation Request Update — {$this->reservation->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.declined',
        );
    }
}
