<x-layouts.customer title="Order #{{ $order->order_number }}">
<div class="panel" data-order-page data-order-id="{{ $order->order_id }}" data-state-url="{{ route('orders.state', $order) }}" data-status="{{ $order->status->value }}" data-testid="order-status-page">
  @if (session('status') === 'order-cancelled')
    <div class="auth-error auth-success" role="status" data-testid="order-cancelled-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Order cancelled.</span>
    </div>
  @elseif (session('status') === 'order-paid')
    <div class="auth-error auth-success" role="status" data-testid="order-paid-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Payment received.</span>
    </div>
  @elseif (session('status') === 'cash-requested')
    <div class="auth-error auth-success" role="status" data-testid="cash-requested-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>A staff member will come to collect payment.</span>
    </div>
  @elseif (session('status') === 'feedback-submitted')
    <div class="auth-error auth-success" role="status" data-testid="feedback-submitted-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Thanks for your feedback.</span>
    </div>
  @endif

  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  <h1>Order #{{ $order->order_number }}</h1>
  <p>Placed @auDateTime($order->placed_at)</p>

  <x-site.order-status-badge :status="$order->status" />

  @if ($order->status->value === 'pending_payment')
    <p data-testid="order-waiting-payment">Waiting for payment to be confirmed.</p>
    <form method="POST" action="{{ route('orders.cancel', $order) }}">
      @csrf
      <button class="btn btn-outline" type="submit" data-testid="order-cancel">Cancel order</button>
    </form>
    <form method="POST" action="{{ route('orders.pay.check', $order) }}">
      @csrf
      <button class="btn btn-outline" type="submit" data-testid="order-check-payment">Check payment status</button>
    </form>
  @elseif ($order->status->value === 'cancelled')
    <p data-testid="order-cancelled">This order was cancelled.</p>
  @elseif (in_array($order->status->value, ['paid', 'preparing', 'ready'], true))
    <p data-testid="order-cancel-unavailable">This order has been paid — ask a staff member if you need to cancel it.</p>
  @endif

  <ol class="order-timeline" data-testid="order-timeline">
    @foreach ($timeline as $step)
      <li data-step="{{ $step['step'] }}" class="order-timeline-step @if($step['done']) is-done @endif @if($step['current']) is-current @endif">
        <span>{{ ucfirst($step['step']) }}</span>
        @if ($step['at'])
          <span>@auDateTime($step['at'])</span>
        @endif
      </li>
    @endforeach
  </ol>

  <div class="order-ready-alert" hidden data-ready-alert data-testid="order-ready-alert">Your order is ready!</div>

  @if ($kitchenEta)
    <p data-testid="order-eta-kitchen">Kitchen: ready between {{ $kitchenEta['from']->format('g:i A') }} and {{ $kitchenEta['to']->format('g:i A') }}</p>
  @endif
  @if ($barEta)
    <p data-testid="order-eta-bar">Bar: ready between {{ $barEta['from']->format('g:i A') }} and {{ $barEta['to']->format('g:i A') }}</p>
  @endif

  <h2>Items</h2>
  @foreach ($order->items as $item)
    <div style="margin-bottom:1rem;border-bottom:1px solid #F0E6D8;padding-bottom:.8rem">
      <strong>{{ $item->quantity }} × {{ $item->item_name }}</strong> — {{ $item->size_name }}
      @if (! empty($item->selected_options))
        <p>{{ collect($item->selected_options)->pluck('name')->implode(', ') }}</p>
      @endif
      @if ($item->special_request)
        <p>{{ $item->special_request }}</p>
      @endif
      <p>@money($item->line_total)</p>
    </div>
  @endforeach

  <p><strong>Total: @money($order->total_amount)</strong></p>

  <p><a href="{{ route('orders.receipt', $order) }}" target="_blank" data-testid="order-receipt-link">Download receipt (PDF)</a></p>

  @if ($order->status->value === 'served')
    @if ($order->feedback)
      <p data-testid="feedback-thanks">You've already rated this order — thanks!</p>
    @elseif ($order->taken_by_staff_id === null)
      <x-feedback-modal :order="$order" />
    @endif
  @endif
</div>
</x-layouts.customer>
