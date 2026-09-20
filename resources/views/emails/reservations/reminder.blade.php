<x-emails.layout
    title="Upcoming Reservation Reminder"
    :reservation="$reservation"
    :action-url="route('reservations.index')"
    action-text="View booking details"
>
  <div style="background-color:#FEF3C7;border:1px solid #FDE68A;color:#92400E;padding:12px 16px;border-radius:6px;font-weight:700;font-size:15px;margin-bottom:16px;">
    🔔 Reminder: Your table reservation is coming up!
  </div>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    We're looking forward to hosting you at Coolaroo Restaurant &amp; Bistro on <strong>{{ $reservation->booking_date->format('l, j F Y') }} at {{ substr($reservation->booking_time, 0, 5) }}</strong>.
  </p>
  <div style="background-color:#FFF8F0;border-left:3px solid #FF6B2C;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#6B5647;">
    <strong>Need to adjust your booking?</strong>
    Please make any modifications online at least 2 hours prior to your scheduled time. Tables are held for up to 15 minutes past your booking time.
  </div>
</x-emails.layout>
