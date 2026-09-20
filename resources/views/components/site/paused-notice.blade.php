@props([
    'type' => 'qr_ordering', // 'qr_ordering', 'reservations', 'ai', 'custom'
    'title' => null,
    'message' => null,
])

@php
  $venuePhone = app(\App\Services\SettingService::class)->get('venue_phone', '03 9300 0000');
  $cleanPhone = preg_replace('/[^0-9+]/', '', $venuePhone);
@endphp

<div class="auth-error" role="status" data-testid="paused-state-notice">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="10" y1="15" x2="10" y2="9"></line><line x1="14" y1="15" x2="14" y2="9"></line></svg>
  <div>
    @if ($type === 'qr_ordering')
      <strong>Online Ordering Paused</strong> &mdash; Table ordering is temporarily paused right now. You can view dishes and prices, but cannot checkout online. Please speak with our waitstaff to order.
    @elseif ($type === 'reservations')
      <strong>Online Bookings Paused</strong> &mdash; Online reservations are paused. Please call us directly at <a href="tel:{{ $cleanPhone }}"><strong>{{ $venuePhone }}</strong></a> to check table availability.
    @elseif ($type === 'ai')
      <strong>Assistant Temporarily Unavailable</strong> &mdash; The dining assistant is currently offline.
    @else
      <strong>{{ $title ?? 'Service Temporarily Paused' }}</strong> &mdash; {{ $message ?? 'Please check back shortly or speak with a staff member.' }}
    @endif
  </div>
</div>
