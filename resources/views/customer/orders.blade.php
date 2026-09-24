<x-layouts.customer title="My orders">
  <div class="wrap customer-container" data-testid="orders-page">
    <x-customer.nav-tabs active="orders" />

    @if (session('status') === 'refund-requested')
      <div class="auth-error auth-success" role="status" data-testid="refund-requested-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <div>
          <strong>Refund requested</strong>
          <span>A manager will review it and be in touch. You can follow it below.</span>
        </div>
      </div>
    @endif

    <div class="customer-page-header">
      <div>
        <h1>My orders</h1>
        <p class="sub">View your order history, payment status and receipts</p>
      </div>
      <div>
        <a href="{{ route('menu.index') }}" class="btn btn-outline btn-sm" data-testid="orders-menu-link">Browse menu</a>
      </div>
    </div>

    @if ($orders->isEmpty())
      <div class="customer-empty-card" data-testid="no-orders-msg">
        <div class="empty-icon" aria-hidden="true">🍽️</div>
        <h3>No orders placed yet</h3>
        <p>When you scan a QR code at your table and place an order, it will appear here in your account.</p>
        <div style="margin-top:1.2rem">
          <a href="{{ route('menu.index') }}" class="btn btn-amber btn-sm" data-testid="browse-menu-btn">Browse our menu</a>
        </div>
      </div>
    @else
      <div class="orders-list" data-testid="orders-list">
        @foreach ($orders as $order)
          <div class="order-card" data-testid="order-card-{{ $order->order_id }}">
            <div class="order-card-head">
              <div class="order-ref-meta">
                <span class="order-number">Order #<strong>{{ $order->order_number }}</strong></span>
                <span class="order-date">@auDateTime($order->placed_at ?? $order->created_at)</span>
              </div>
              <div class="order-badges">
                <x-site.order-status-badge :status="$order->status" />
                @php
                  $refundPending = $order->refunds
                    ->whereIn('status', [\App\Enums\RefundStatus::Requested, \App\Enums\RefundStatus::Processing])
                    ->isNotEmpty();
                @endphp
                @if ($order->payment_status === \App\Enums\PaymentStatus::Refunded)
                  <span class="order-badge-refund" data-testid="order-refund-badge-{{ $order->order_id }}">Refunded</span>
                @elseif ($order->payment_status === \App\Enums\PaymentStatus::PartiallyRefunded)
                  <span class="order-badge-refund" data-testid="order-refund-badge-{{ $order->order_id }}">Partly refunded</span>
                @elseif ($refundPending)
                  <span class="order-badge-refund-pending" data-testid="order-refund-badge-{{ $order->order_id }}">Refund requested</span>
                @endif
              </div>
            </div>

            <div class="order-card-body">
              <div class="order-details-col">
                <p><strong>Table:</strong> {{ $order->restaurantTable ? 'Table ' . $order->restaurantTable->table_number : 'Takeaway / Bar' }}</p>
                <p><strong>Items:</strong> {{ $order->items->sum('quantity') }} items ({{ $order->items->pluck('item_name')->take(3)->implode(', ') }}{{ $order->items->count() > 3 ? '...' : '' }})</p>
                @php
                  $payment = $order->payments->first();
                @endphp
                <p><strong>Payment:</strong> <span class="payment-method-text">{{ $payment ? ucfirst($payment->method->value) : 'Pending' }} &bull; {{ ucfirst(str_replace('_', ' ', $order->payment_status->value)) }}</span></p>
              </div>
              <div class="order-total-col">
                <span class="order-total-label">Total</span>
                <span class="order-total-amount">@money($order->total_amount)</span>
              </div>
            </div>

            @if ($order->refunds->isNotEmpty())
              <div class="order-refunds" data-testid="order-refunds-{{ $order->order_id }}">
                <h3 class="order-refunds-title">Refund requests</h3>
                @foreach ($order->refunds as $refund)
                  <x-customer.refund-status :refund="$refund" />
                @endforeach
              </div>
            @endif

            <div class="order-card-actions">
              <a href="{{ route('orders.show', $order) }}" class="btn btn-outline btn-sm" data-testid="view-order-{{ $order->order_id }}">
                View order status &rarr;
              </a>
              @if ($order->payment_status->value === 'paid')
                <a href="{{ route('orders.receipt', $order) }}" class="btn btn-sm btn-subtle" target="_blank" data-testid="receipt-order-{{ $order->order_id }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false" style="width:14px;height:14px;display:inline-block;vertical-align:-2px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                  Download receipt (PDF)
                </a>
              @endif
              @if ($refundableLines[$order->order_id]->isNotEmpty())
                <button type="button" class="btn btn-sm btn-subtle" data-open-modal="refund-modal-{{ $order->order_id }}" data-testid="refund-order-{{ $order->order_id }}">
                  Request a refund
                </button>
              @endif
            </div>
          </div>

          @if ($refundableLines[$order->order_id]->isNotEmpty())
            <x-refund-request :order="$order" :lines="$refundableLines[$order->order_id]" />
          @endif
        @endforeach
      </div>

      <div class="customer-pagination" data-testid="orders-pagination">
        {{ $orders->links() }}
      </div>
    @endif
  </div>
</x-layouts.customer>
