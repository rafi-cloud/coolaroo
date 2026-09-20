<x-emails.layout
    title="Reservation Request Received"
    :reservation="$reservation"
    :action-url="route('reservations.index')"
    action-text="View my reservations"
>
  <h2 style="font-size:18px;color:#2B1A10;margin-top:0;margin-bottom:12px;">We've received your booking request!</h2>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    Thank you for requesting a table at Coolaroo Restaurant &amp; Bistro. Your booking request has been submitted and is currently <strong>awaiting staff review</strong>.
  </p>
  <p style="margin-bottom:14px;color:#6B5647;">
    Our team will review floor availability and confirm your booking shortly. You will receive another notification once your reservation is confirmed.
  </p>
</x-emails.layout>
