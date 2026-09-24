@props(['refund'])
@php
  $stage = match ($refund->status->value) {
    'requested' => 1,
    'processing' => 2,
    'completed' => 3,
    default => 0,
  };
  $settled = in_array($refund->status->value, ['rejected', 'failed'], true);
@endphp
<div class="refund-progress" data-testid="refund-progress-{{ $refund->refund_id }}">
  <span class="badge b-{{ $refund->status->value }}">{{ ucfirst($refund->status->value) }}</span>
  <span class="refund-progress-qty">{{ $refund->quantity }} × @money($refund->amount)</span>

  @if ($settled)
    <span class="refund-progress-note">
      {{ $refund->status->value === 'rejected' ? 'Declined by the manager' : 'Card refund failed — the manager can retry it' }}
      @if ($refund->rejection_reason) &mdash; {{ $refund->rejection_reason }} @endif
    </span>
  @else
    <ol class="refund-progress-steps">
      <li @class(['is-done' => $stage >= 1])>Requested</li>
      <li @class(['is-done' => $stage >= 2])>With the manager</li>
      <li @class(['is-done' => $stage >= 3])>Money back</li>
    </ol>
  @endif

  <span class="refund-progress-meta">asked @auDateTime($refund->requested_at) by {{ $refund->requesterName() }}</span>
</div>
