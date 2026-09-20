<x-emails.layout
    title="Reservation Cancelled"
    :reservation="$reservation"
    :action-url="url('/#reserve')"
    action-text="Book another table"
>
  <h2 style="font-size:18px;color:#2B1A10;margin-top:0;margin-bottom:12px;">Reservation Cancelled</h2>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    This email confirms that your table reservation <strong>{{ $reservation->reference_code }}</strong> for {{ $reservation->booking_date->format('l, j F Y') }} at {{ substr($reservation->booking_time, 0, 5) }} has been cancelled.
  </p>
  @if ($reason)
    <div style="background-color:#FFF8F0;border-left:3px solid #FFB627;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#6B5647;">
      <strong>Cancellation note:</strong> {{ $reason }}
    </div>
  @endif
  @if ($reservation->is_late_cancellation)
    <p style="font-size:13px;color:#8C7768;margin-bottom:14px;">
      <em>Note: This cancellation was recorded within 2 hours of the scheduled booking time.</em>
    </p>
  @endif
  <p style="margin-bottom:14px;color:#6B5647;">
    We hope to welcome you another time! You can book a table with us on our website whenever you're ready.
  </p>
</x-emails.layout>
