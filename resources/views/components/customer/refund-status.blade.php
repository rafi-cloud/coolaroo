@props(['refund'])
@php
  $status = $refund->status->value;
  $stage = match ($status) {
    'requested' => 1,
    'processing' => 2,
    'completed' => 3,
    default => 0,
  };
  $settled = in_array($status, ['rejected', 'failed'], true);
  $tone = match ($status) {
    'completed' => 'badge-green',
    'rejected', 'failed' => 'badge-red',
    'processing' => 'badge-amber',
    default => 'badge-gray',
  };
@endphp
<div class="refund-track" data-testid="refund-status-{{ $refund->refund_id }}">
  <div class="refund-track-head">
    <span class="badge {{ $tone }}">{{ ucfirst($status) }}</span>
    <span class="refund-track-item">{{ $refund->orderItem?->item_name ?? 'Item' }} &middot; {{ $refund->quantity }} × @money($refund->amount)</span>
  </div>

  @if ($settled)
    <p class="refund-track-note">
      {{ $status === 'rejected' ? 'The manager declined this request.' : 'The card refund did not go through. The manager can retry it.' }}
      @if ($refund->rejection_reason) {{ $refund->rejection_reason }} @endif
    </p>
  @else
    <ol class="refund-track-steps">
      <li @class(['is-done' => $stage >= 1])>Requested</li>
      <li @class(['is-done' => $stage >= 2])>With the manager</li>
      <li @class(['is-done' => $stage >= 3])>Money back</li>
    </ol>
  @endif

  <p class="refund-track-meta">
    Asked @auDateTime($refund->requested_at)
    @if ($refund->completed_at) &middot; settled @auDateTime($refund->completed_at) @endif
  </p>
</div>
