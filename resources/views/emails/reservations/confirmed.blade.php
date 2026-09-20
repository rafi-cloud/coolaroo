<x-emails.layout
    title="Reservation Confirmed!"
    :reservation="$reservation"
    :action-url="route('reservations.index')"
    action-text="View booking details"
>
  <div style="background-color:#DEF7EC;border:1px solid #BCF0DA;color:#03543F;padding:12px 16px;border-radius:6px;font-weight:700;font-size:15px;margin-bottom:16px;">
    ✓ Your reservation is confirmed!
  </div>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    Great news! Your table booking at Coolaroo Restaurant &amp; Bistro has been approved and confirmed. We look forward to welcoming you and your guests.
  </p>
  <div style="background-color:#FFF8F0;border-left:3px solid #FFB627;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#6B5647;">
    <strong>Arrival Notice (15-Minute Grace Period):</strong> Tables are held for up to 15 minutes after your scheduled booking time. If your party is running late, please give us a call so we can keep your table ready.
  </div>
</x-emails.layout>
