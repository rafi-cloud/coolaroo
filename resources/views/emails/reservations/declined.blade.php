<x-emails.layout
    title="Reservation Request Update"
    :reservation="$reservation"
    :action-url="url('/#reserve')"
    action-text="Find another time"
>
  <h2 style="font-size:18px;color:#2B1A10;margin-top:0;margin-bottom:12px;">Reservation Request Update</h2>
  <p style="margin-bottom:14px;">
    Hello {{ $reservation->customer?->full_name ?? $reservation->guest_name ?? 'there' }},
  </p>
  <p style="margin-bottom:14px;">
    Thank you for your interest in dining with us. Unfortunately, we are unable to accommodate your reservation request for <strong>{{ $reservation->reference_code }}</strong> at the requested time.
  </p>
  @if ($reason)
    <div style="background-color:#FDE8E8;border-left:3px solid #E02424;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#9B1C1C;">
      <strong>Note from venue:</strong> {{ $reason }}
    </div>
  @endif
  <p style="margin-bottom:14px;color:#6B5647;">
    We encourage you to select an alternative date or time slot, or call our team directly to explore other seating options.
  </p>
</x-emails.layout>
