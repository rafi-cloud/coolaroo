<x-layouts.customer title="Order #{{ $order->order_number }}" :table-label="$tableLabel ?? null">
<div class="wrap order-page" data-order-page data-order-id="{{ $order->order_id }}" data-state-url="{{ route('orders.state', $order) }}" data-status="{{ $order->status->value }}" data-payment-status="{{ $order->payment_status->value }}" data-testid="order-status-page">
  @if (session('status') === 'order-cancelled')
    <div class="auth-error auth-warning" role="status" data-testid="order-cancelled-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <span>Order cancelled.</span>
    </div>
  @elseif (session('status') === 'order-paid')
    <div class="auth-error auth-success order-banner-paid" role="status" data-testid="order-paid-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <div>
        <strong>Payment Confirmed!</strong>
        <span>Payment received.</span>
      </div>
    </div>
  @elseif (session('status') === 'cash-requested')
    <div class="auth-error auth-success order-banner-cash" role="status" data-testid="cash-requested-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <div>
        <strong>Assistance Requested</strong>
        <span>A staff member will come to collect payment.</span>
      </div>
    </div>
  @elseif (session('status') === 'refund-requested')
    <div class="auth-error auth-success" role="status" data-testid="refund-requested-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <div>
        <strong>Refund requested</strong>
        <span>A manager will review it and be in touch.</span>
      </div>
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

  <div class="order-header">
    <div class="order-header-left">
      <a href="{{ route('orders.index') }}" class="order-back-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>My orders</span>
      </a>
      <div class="order-title-row">
        <h1 class="order-title">Order #{{ $order->order_number }}</h1>
        <x-site.order-status-badge :status="$order->status" />
      </div>
      <p class="order-placed-meta">Placed @auDateTime($order->placed_at)</p>
    </div>
    @if ($table ?? null)
      <div class="order-table-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        <span>Table {{ $table->table_number }}</span>
      </div>
    @endif
  </div>

  <div class="order-layout">
    {{-- Left Column: Tracking Hero Card --}}
    <div class="order-main-col">
      <div class="order-track-card">
        <div class="order-track-head">
          <div>
            <span class="order-track-eyebrow">Real-time Tracker</span>
            <h2 class="order-track-heading">
              @if ($order->status->value === 'pending_payment')
                Awaiting Payment Confirmation
              @elseif ($order->status->value === 'paid')
                Order Confirmed &amp; Queued
              @elseif ($order->status->value === 'preparing')
                Preparing in the Kitchen
              @elseif ($order->status->value === 'ready')
                Order Ready for Service
              @elseif ($order->status->value === 'served')
                Delivered to Your Table
              @elseif ($order->status->value === 'cancelled')
                Order Cancelled
              @endif
            </h2>
          </div>
          <div class="order-live-indicator" aria-label="Real-time order sync active">
            <span class="order-live-dot"></span>
            <span>Live kitchen sync</span>
          </div>
        </div>

        {{-- Status narrative message --}}
        <div class="order-status-narrative">
          @if ($order->status->value === 'pending_payment')
            @if ($order->hasPendingCashRequest())
              <p class="order-narrative-p" data-testid="order-waiting-cash">Waiting for a staff member to collect your cash payment.</p>
            @else
              <p class="order-narrative-p" data-testid="order-waiting-payment">Waiting for payment to be confirmed.</p>
            @endif
          @elseif ($order->status->value === 'cancelled')
            <p class="order-narrative-p order-narrative-cancelled" data-testid="order-cancelled">This order was cancelled.</p>
          @elseif (in_array($order->status->value, ['paid', 'preparing', 'ready'], true))
            <p class="order-narrative-p" data-testid="order-cancel-unavailable">This order has been paid — ask a staff member if you need to cancel it.</p>
          @elseif ($order->status->value === 'served')
            <p class="order-narrative-p order-narrative-served">Your meal has been served to your table. We hope you enjoy your meal!</p>
          @endif
        </div>

        {{-- The Tracking Bar (Timeline) --}}
        <div class="order-timeline-wrapper">
          <ol class="order-timeline" data-testid="order-timeline">
            @foreach ($timeline as $step)
              <li data-step="{{ $step['step'] }}" class="order-timeline-step @if($step['done']) is-done @endif @if($step['current']) is-current @endif">
                <div class="order-step-node">
                  <span class="order-step-num">{{ $loop->iteration }}</span>
                  <svg class="order-step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="order-step-info">
                  <span class="order-step-label">{{ ucfirst($step['step']) }}</span>
                  <span class="order-step-sub">
                    @if ($step['step'] === 'paid')
                      Payment received
                    @elseif ($step['step'] === 'preparing')
                      In the kitchen
                    @elseif ($step['step'] === 'ready')
                      Ready to serve
                    @elseif ($step['step'] === 'served')
                      Delivered to table
                    @endif
                  </span>
                  @if ($step['at'])
                    <time class="order-step-time">@auDateTime($step['at'])</time>
                  @endif
                </div>
              </li>
            @endforeach
          </ol>
        </div>

        {{-- Order Ready Alert --}}
        <div class="order-ready-alert" @if($order->status->value !== 'ready') hidden @endif data-ready-alert data-testid="order-ready-alert">
          <svg class="order-ready-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:24px;height:24px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <div class="order-ready-text">
            <strong>Your order is ready!</strong>
            <span>Please collect from the bar/counter or our waitstaff will bring it to Table {{ $table?->table_number ?? '' }}.</span>
          </div>
        </div>

        {{-- Kitchen & Bar Live ETAs --}}
        <div class="order-etas-grid" data-eta-grid @if (! $kitchenEta && ! $barEta) hidden @endif>
          <div class="order-eta-card" data-testid="order-eta-kitchen" @if (! $kitchenEta) hidden @endif>@if ($kitchenEta)Kitchen: ready between @auTime($kitchenEta['from']) and @auTime($kitchenEta['to'])@endif</div>
          <div class="order-eta-card" data-testid="order-eta-bar" @if (! $barEta) hidden @endif>@if ($barEta)Bar: ready between @auTime($barEta['from']) and @auTime($barEta['to'])@endif</div>
        </div>

        {{-- Pending Payment Action Box --}}
        @if ($order->status->value === 'pending_payment')
          @if ($order->hasPendingCashRequest())
            <div class="order-pay-actions-box" data-testid="order-cash-waiting">
              <div class="order-pay-actions-prompt">
                <strong>Waiting for cash payment</strong>
                <span>A staff member is on their way to collect @money($order->total_amount). Your order goes to the kitchen as soon as they record it.</span>
              </div>
              <div class="order-pay-actions-btns">
                <a href="{{ route('orders.pay.show', $order) }}" class="btn btn-outline" data-testid="order-pay-by-card">Pay by card instead</a>
                <form method="POST" action="{{ route('orders.cancel', $order) }}">
                  @csrf
                  <button class="btn btn-subtle-danger" type="submit" data-testid="order-cancel">Cancel order</button>
                </form>
              </div>
            </div>
          @else
            <div class="order-pay-actions-box">
              <div class="order-pay-actions-prompt">
                <strong>Complete Payment</strong>
                <span>Your order will be sent to the kitchen as soon as payment is confirmed.</span>
              </div>
              <div class="order-pay-actions-btns">
                <a href="{{ route('orders.pay.show', $order) }}" class="btn btn-orange">
                  <span>Pay @money($order->total_amount) now &rarr;</span>
                </a>
                <form method="POST" action="{{ route('orders.pay.check', $order) }}">
                  @csrf
                  <button class="btn btn-outline" type="submit" data-testid="order-check-payment">Check payment status</button>
                </form>
                <form method="POST" action="{{ route('orders.cancel', $order) }}">
                  @csrf
                  <button class="btn btn-subtle-danger" type="submit" data-testid="order-cancel">Cancel order</button>
                </form>
              </div>
            </div>
          @endif
        @endif

        {{-- FR51: raise a refund request on a paid order, inside the 24-hour window --}}
        @if ($refundableLines->isNotEmpty())
          <x-refund-request :order="$order" :lines="$refundableLines" />
        @endif

        {{-- Feedback Section when served --}}
        @if ($order->status->value === 'served')
          <div class="order-feedback-section">
            @if ($order->feedback)
              <div class="order-feedback-done" data-testid="feedback-thanks">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px;color:#1F7844;"><path d="M20 6L9 17l-5-5"/></svg>
                <span>You've already rated this order — thanks!</span>
              </div>
            @else
              @can('create', [\App\Models\Feedback::class, $order])
                <div class="order-feedback-card">
                  <x-feedback-modal :order="$order" />
                </div>
              @endcan
            @endif
          </div>
        @endif
      </div>
    </div>

    {{-- Right Column: Order Details & Receipt Summary --}}
    <div class="order-sidebar-col">
      <div class="order-details-card">
        <div class="order-details-head">
          <h2 class="order-details-title">Items</h2>
          <span class="order-items-count">{{ $order->items->sum('quantity') }} items</span>
        </div>

        <div class="order-items-scroll">
          @foreach ($order->items as $item)
            <div class="order-detail-item">
              <div class="order-item-thumb">
                <img src="{{ asset($item->menuItem?->image_url ?? 'images/dish-burger.jpg') }}" alt="{{ $item->item_name }}" loading="lazy" width="56" height="56">
              </div>
              <div class="order-item-info">
                <div class="order-item-row">
                  <h3 class="order-item-name">{{ $item->quantity }} × {{ $item->item_name }}</h3>
                  <span class="order-item-price">@money($item->line_total)</span>
                </div>
                <div class="order-item-meta">
                  <span class="order-size-name">{{ $item->size_name }}</span>
                </div>
                @if (! empty($item->selected_options))
                  <p class="order-options-sub">{{ collect($item->selected_options)->pluck('name')->implode(', ') }}</p>
                @endif
                @if ($item->special_request)
                  <p class="order-note-sub">Note: {{ $item->special_request }}</p>
                @endif
              </div>
            </div>
          @endforeach
        </div>

        <div class="order-summary-totals">
          <div class="order-totals-row">
            <span>Subtotal</span>
            <span>@money($order->total_amount - ($order->gst_amount ?? ($order->total_amount / 11)))</span>
          </div>
          <div class="order-totals-row">
            <span>Includes GST (10%)</span>
            <span>@money($order->gst_amount ?? ($order->total_amount / 11))</span>
          </div>
          <div class="order-totals-row order-total-grand">
            <span>Total: @money($order->total_amount)</span>
          </div>
        </div>

        @php
          $latestPayment = $order->payments->last(fn ($payment) => $payment->status->value === 'succeeded')
            ?? $order->payments->last();
        @endphp
        <div class="order-payment-status-block">
          <div class="order-payment-meta">
            <span class="order-payment-label">Payment</span>
            <span class="order-payment-val">
              @if ($latestPayment)
                {{ ucfirst($latestPayment->method->value) }} &bull; {{ ucfirst(str_replace('_', ' ', $latestPayment->status->value)) }}
              @else
                {{ ucfirst(str_replace('_', ' ', $order->payment_status->value)) }}
              @endif
            </span>
          </div>
          @if ($order->payment_status->value === 'paid')
            <span class="order-paid-badge-pill">Paid</span>
          @else
            <span class="order-unpaid-badge-pill">Unpaid</span>
          @endif
        </div>

        <div class="order-receipt-action">
          <a href="{{ route('orders.receipt', $order) }}" target="_blank" data-testid="order-receipt-link" class="btn btn-outline btn-block order-receipt-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Download receipt (PDF)</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
</x-layouts.customer>
