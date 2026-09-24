<x-layouts.customer title="Pay for order #{{ $order->order_number }}" :table-label="$tableLabel ?? null">
<div class="wrap pay-page">
  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if (! empty(session('removed_items')))
    <div class="pay-alert-warning" data-testid="pay-removed-items" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:20px;height:20px;flex:none;color:#C05621;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <strong>Some items are no longer available</strong>
        <span>Removed from this order: {{ implode(', ', session('removed_items')) }}</span>
      </div>
    </div>
  @endif

  <div class="pay-header">
    <div>
      <a href="{{ route('orders.index') }}" class="pay-back-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>My Orders</span>
      </a>
      <div class="pay-title-row">
        <h1 class="pay-title">Order #{{ $order->order_number }}</h1>
        <span class="pay-badge-status">Awaiting Payment</span>
      </div>
    </div>
    @if ($table ?? null)
      <div class="pay-table-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        <span>Table {{ $table->table_number }}</span>
      </div>
    @endif
  </div>

  <div class="pay-layout">
        <div class="pay-methods-column">
      <div class="pay-methods-card">
        <h2 class="pay-methods-heading">Select Payment Method</h2>
        <p class="pay-methods-sub">Choose how you would like to settle this order:</p>

        <div class="pay-options-list">
                    <div class="pay-option-card is-primary">
            <div class="pay-option-badge">Instant &bull; Recommended</div>
            <div class="pay-option-header">
              <div class="pay-option-icon pay-icon-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              </div>
              <div class="pay-option-text">
                <h3 class="pay-option-title">Credit / Debit Card &bull; Apple Pay &bull; Google Pay</h3>
                <p class="pay-option-desc">Fast, encrypted online checkout powered by Stripe. Kitchen will start preparing your order immediately upon completion.</p>
                <div class="pay-card-brands" aria-hidden="true">
                  <span class="pay-brand-pill">VISA</span>
                  <span class="pay-brand-pill">Mastercard</span>
                  <span class="pay-brand-pill">Apple Pay</span>
                  <span class="pay-brand-pill">Google Pay</span>
                </div>
              </div>
            </div>

            <div class="pay-option-action">
              <form method="POST" action="{{ route('orders.pay.stripe', $order) }}">
                @csrf
                <button class="btn btn-orange btn-block pay-submit-btn" type="submit" data-testid="pay-stripe">
                  <span>Pay @money($order->total_amount) with Card &rarr;</span>
                </button>
              </form>
              <div class="pay-security-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>256-bit encrypted checkout via Stripe &bull; PCI-DSS compliant</span>
              </div>
            </div>
          </div>

                    <div class="pay-option-card is-secondary">
            <div class="pay-option-header">
              <div class="pay-option-icon pay-icon-cash">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
              </div>
              <div class="pay-option-text">
                <h3 class="pay-option-title">Pay with Cash to Waitstaff</h3>
                <p class="pay-option-desc">Prefer cash? Request assistance and a team member will visit Table {{ $table?->table_number ?? 'your table' }} with your bill to collect cash and provide change.</p>
              </div>
            </div>

            <div class="pay-option-action">
              <form method="POST" action="{{ route('orders.pay.cash', $order) }}">
                @csrf
                <button class="btn btn-outline btn-block pay-submit-btn-cash" type="submit" data-testid="pay-cash">
                  <span>Pay with cash at table</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

        <div class="pay-summary-column">
      <div class="pay-summary-card">
        <h2 class="pay-summary-title">Order Details</h2>

        @if ($table ?? null)
          <div class="pay-summary-table-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div>
              <strong>Table {{ $table->table_number }} &bull; Dine-in</strong>
              <span>Dishes will be delivered directly to your table</span>
            </div>
          </div>
        @endif

        @if ($order->items && $order->items->isNotEmpty())
          <div class="pay-items-list">
            @foreach ($order->items as $item)
              <div class="pay-item-row">
                <div class="pay-item-info">
                  <span class="pay-item-qty">{{ $item->quantity }}&times;</span>
                  <div>
                    <strong class="pay-item-name">{{ $item->item_name }}</strong>
                    <span class="pay-item-size">{{ $item->size_name }}</span>
                    @if (! empty($item->selected_options))
                      <span class="pay-item-options">{{ implode(', ', array_column($item->selected_options, 'option_name')) }}</span>
                    @endif
                    @if ($item->special_request)
                      <span class="pay-item-request">Note: {{ $item->special_request }}</span>
                    @endif
                  </div>
                </div>
                <span class="pay-item-price">@money($item->line_total)</span>
              </div>
            @endforeach
          </div>
        @endif

        <div class="pay-summary-divider"></div>

        <div class="pay-summary-breakdown">
          <div class="pay-summary-row">
            <span>Items ({{ $order->items ? $order->items->sum('quantity') : 0 }})</span>
            <span>@money($order->total_amount)</span>
          </div>
          <div class="pay-summary-row pay-summary-gst">
            <span>Includes GST (total ÷ 11)</span>
            <span>@money($order->gst_amount ?? ($order->total_amount / 11))</span>
          </div>
          <div class="pay-summary-row pay-summary-service">
            <span>Table service</span>
            <span class="pay-badge-free">FREE</span>
          </div>
        </div>

        <div class="pay-summary-divider"></div>

        <div class="pay-summary-total-row">
          <span class="pay-summary-total-label">Total Amount</span>
          <span class="pay-summary-total-amount">@money($order->total_amount)</span>
        </div>
      </div>
    </div>
  </div>
</div>
</x-layouts.customer>
