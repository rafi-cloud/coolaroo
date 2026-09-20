<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationConfirmedMail extends QueuedMailable
{
    public function __construct(
        public Reservation $reservation,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reservation Confirmed! — {$this->reservation->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservations.confirmed',
        );
    }
}
