<x-emails.layout
    title="Reservation Request Expired"
    :reservation="$reservation"
    :action-url="url('/#reserve')"
    action-text="Make a new booking"
>
  <h2 style="font-size:18px;color:#2B1A10;margin-top:0;margin-bottom:12px;">Reservation Request Expired</h2>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    Your reservation request <strong>{{ $reservation->reference_code }}</strong> for {{ $reservation->booking_date->format('l, j F Y') }} has expired because the requested booking time has arrived without confirmation.
  </p>
  <p style="margin-bottom:14px;color:#6B5647;">
    We apologize for any inconvenience. If you would still like to dine with us today or in the future, please make a new booking online or give our venue a call directly.
  </p>
</x-emails.layout>
