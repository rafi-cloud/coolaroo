<x-layouts.customer title="Pay for order #{{ $order->order_number }}">
<div class="panel">
  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if (! empty(session('removed_items')))
    <p data-testid="pay-removed-items">Removed (no longer available): {{ implode(', ', session('removed_items')) }}</p>
  @endif

  <h1>Order #{{ $order->order_number }}</h1>
  <p>Total: @money($order->total_amount)</p>

  <form method="POST" action="{{ route('orders.pay.stripe', $order) }}">
    @csrf
    <button class="btn btn-orange" type="submit" data-testid="pay-stripe">Pay with card</button>
  </form>
</div>
</x-layouts.customer>
