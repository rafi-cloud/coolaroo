<x-layouts.customer title="Your cart" :table-label="$tableLabel ?? null">
<div class="wrap cart-page">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>{{ session('status') === 'cart-updated' ? 'Cart updated successfully.' : (session('status') === 'line-removed' ? 'Item removed from your cart.' : (session('status') === 'cart-cleared' ? 'Your cart has been cleared.' : 'Saved.')) }}</span>
    </div>
  @endif

  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if ($errors->has('cart'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first('cart') }}</span>
    </div>
  @endif

  @if (! ($qrOrderingEnabled ?? true))
    <div class="cart-paused-banner" style="margin-bottom:1.5rem">
      <x-site.paused-notice type="qr_ordering" />
    </div>
  @endif

  <div class="cart-header">
    <div>
      <a href="{{ route('menu.index') }}" class="cart-back-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>Back to menu</span>
      </a>
      <h1 class="cart-title">Your Cart</h1>
    </div>
    @if ($table ?? null)
      <div class="cart-table-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        <span>Table {{ $table->table_number }}</span>
      </div>
    @endif
  </div>

  @if (count($lines) > 0)
    <div class="cart-layout">
            <div class="cart-items-column">
        <div class="cart-items-card">
          <div class="cart-items-header">
            <span>Dish &amp; Add-ons</span>
            <span>Quantity &amp; Total</span>
          </div>

          <div class="cart-items-list">
            @foreach ($lines as $line)
              <article class="cart-item">
                <div class="cart-item-main">
                  <div class="cart-item-thumb">
                    <img src="{{ asset($line['item']->image_url ?? 'images/dish-burger.jpg') }}" alt="{{ $line['item']->item_name }}" loading="lazy" width="72" height="72">
                  </div>

                  <div class="cart-item-info">
                    <div class="cart-item-title-row">
                      <h2 class="cart-item-name">{{ $line['item']->item_name }}</h2>
                    </div>

                    <div class="cart-item-meta">
                      <span class="cart-size-pill">{{ $line['size']->size_name }}</span>
                      <span class="cart-item-unit-price">@money($line['unit_price']) each</span>
                    </div>

                    @if ($line['options']->isNotEmpty())
                      <div class="cart-options-list">
                        @foreach ($line['options'] as $opt)
                          <span class="cart-option-tag">+ {{ $opt->option_name }} <small>(@money($opt->price_delta))</small></span>
                        @endforeach
                      </div>
                    @endif
                  </div>
                </div>

                <div class="cart-item-actions">
                  <form method="POST" action="{{ route('cart.lines.update', $line['line_id']) }}" class="cart-line-form">
                    @csrf
                    @method('PATCH')

                    <div class="cart-qty-group">
                      <label class="visually-hidden" for="qty-{{ $line['line_id'] }}">Quantity for {{ $line['item']->item_name }}</label>
                      <div class="cart-qty-stepper">
                        <button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Decrease quantity">-</button>
                        <input type="number" id="qty-{{ $line['line_id'] }}" name="quantity" min="1" max="99" value="{{ $line['quantity'] }}" aria-label="Quantity for {{ $line['item']->item_name }}" data-testid="cart-quantity-{{ $line['line_id'] }}" class="cart-qty-input">
                        <button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Increase quantity">+</button>
                      </div>
                    </div>

                    <div class="cart-special-request-group">
                      <label class="visually-hidden" for="special-{{ $line['line_id'] }}">Special request for {{ $line['item']->item_name }}</label>
                      <input type="text" id="special-{{ $line['line_id'] }}" name="special_request" maxlength="200" value="{{ $line['special_request'] }}" placeholder="Special kitchen request..." aria-label="Special request for {{ $line['item']->item_name }}" data-testid="cart-special-request-{{ $line['line_id'] }}" class="cart-special-input">
                    </div>

                    <button class="cart-btn-update" type="submit" data-testid="cart-update-{{ $line['line_id'] }}">
                      Update
                    </button>
                  </form>

                  <div class="cart-item-price-remove">
                    <span class="cart-line-total">@money($line['line_total'])</span>
                    <form method="POST" action="{{ route('cart.lines.destroy', $line['line_id']) }}">
                      @csrf
                      @method('DELETE')
                      <button class="cart-btn-remove" type="submit" data-testid="cart-remove-{{ $line['line_id'] }}" aria-label="Remove {{ $line['item']->item_name }} from cart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:15px;height:15px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Remove</span>
                      </button>
                    </form>
                  </div>
                </div>
              </article>
            @endforeach
          </div>

          <div class="cart-items-footer">
            <a href="{{ route('menu.index') }}" class="btn btn-outline cart-btn-more">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;display:inline-block;vertical-align:-2px;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
              Add more dishes
            </a>
            <form method="POST" action="{{ route('cart.clear') }}">
              @csrf
              @method('DELETE')
              <button class="cart-btn-clear" type="submit" data-testid="cart-clear">
                Clear entire cart
              </button>
            </form>
          </div>
        </div>
      </div>

            <div class="cart-summary-column">
        <div class="cart-summary-card">
          <h2 class="cart-summary-title">Order Summary</h2>

          @if ($table ?? null)
            <div class="cart-summary-table-notice">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <div>
                <strong>Dine-in at Table {{ $table->table_number }}</strong>
                <span>Orders will be brought directly to your table</span>
              </div>
            </div>
          @endif

          <div class="cart-summary-breakdown">
            <div class="cart-summary-row">
              <span>Items ({{ count($lines) }} {{ count($lines) === 1 ? 'line' : 'lines' }})</span>
              <span>@money($total)</span>
            </div>
            <div class="cart-summary-row cart-summary-gst">
              <span>Includes GST (total ÷ 11)</span>
              <span>@money($total / 11)</span>
            </div>
            <div class="cart-summary-row cart-summary-service">
              <span>Table service</span>
              <span class="cart-badge-free">FREE</span>
            </div>
          </div>

          <div class="cart-summary-divider"></div>

          <div class="cart-summary-total-row">
            <span class="cart-summary-total-label">Total to pay</span>
            <span class="cart-summary-total-amount">@money($total)</span>
          </div>

          @if (! ($qrOrderingEnabled ?? true))
            <div class="cart-paused-wrapper">
              <x-site.paused-notice type="qr_ordering" />
              <button class="btn btn-orange btn-block cart-checkout-btn-disabled" type="button" disabled data-testid="cart-checkout-disabled">
                Ordering Currently Paused
              </button>
            </div>
          @else
            <form method="POST" action="{{ route('checkout.store') }}">
              @csrf
              <button class="btn btn-orange cart-checkout-btn" type="submit" data-testid="cart-checkout">
                Proceed to Checkout &rarr;
              </button>
            </form>
            <div class="cart-checkout-guarantee">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Instant kitchen routing &bull; Card or Cash at table</span>
            </div>
          @endif
        </div>
      </div>
    </div>
  @else
        <div class="cart-empty-card">
      <div class="cart-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      </div>
      <h2 class="cart-empty-heading">Your cart is empty</h2>
      <p class="cart-empty-text" data-testid="cart-empty">Your cart is empty.</p>
      <p class="cart-empty-sub">Explore our wood-fired pizzas, signature burgers, fresh seafood, and chef specials from the menu.</p>
      <a href="{{ route('menu.index') }}" class="btn btn-orange cart-empty-btn">
        Browse the Menu &rarr;
      </a>
    </div>
  @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  function markDirty(input) {
    var form = input.closest('form');
    if (!form) return;
    var btn = form.querySelector('.cart-btn-update');
    if (btn) btn.classList.add('cart-btn-update-highlight');
  }

  document.querySelectorAll('.cart-qty-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var stepper = this.closest('.cart-qty-stepper');
      if (!stepper) return;
      var input = stepper.querySelector('.cart-qty-input');
      if (!input) return;
      var val = parseInt(input.value, 10) || 1;
      if (this.classList.contains('cart-qty-plus')) {
        input.value = Math.min(val + 1, 99);
      } else if (this.classList.contains('cart-qty-minus')) {
        input.value = Math.max(val - 1, 1);
      }
      markDirty(input);
    });
  });

  document.querySelectorAll('.cart-qty-input, .cart-special-input').forEach(function (input) {
    input.addEventListener('input', function () {
      markDirty(this);
    });
  });
});
</script>
@endpush
</x-layouts.customer>
