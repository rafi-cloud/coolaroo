<x-layouts.staff title="Order #{{ $order->order_number }}" page-title="Take payment" page-sub="Table {{ $table->table_number }}">
<div data-testid="staff-order-payment">

  @if (session('status') === 'order-paid')
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Payment received.</span>
    </div>
  @endif

  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  <p>Order #{{ $order->order_number }} &mdash; @money($order->total_amount)</p>

  <a class="btn btn-solid" href="{{ route('staff.orders.stripe-qr', $order) }}" data-testid="staff-order-pay-card">Show card QR</a>

  <x-floor.cash-modal :order="$order" :amount-due="$amountDue" :rounding-amount="$roundingAmount" />
</div>
</x-layouts.staff>
