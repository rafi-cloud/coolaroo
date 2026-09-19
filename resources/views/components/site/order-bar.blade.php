@props(['label'])
<header class="order-bar">
  <div class="wrap order-bar-inner">
    <a class="logo" href="{{ url('/') }}" data-testid="order-bar-home">
      <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant">
      <span class="logo-text"><strong>COOLAROO</strong><span>RESTAURANT &amp; BISTRO</span></span>
    </a>
    <span class="table-chip">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/><rect x="14" y="14" width="7" height="7" rx="1.3"/></svg>
      {{ $label }}
    </span>
    <a class="cart-link" href="{{ url('/cart') }}" data-testid="order-bar-cart">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      <span class="cart-count" data-testid="order-bar-cart-count">{{ app(\App\Services\CartService::class)->count() }}</span>
    </a>
  </div>
</header>