<header class="order-bar">
  <div class="wrap order-bar-inner">
    <a class="logo" href="{{ url('/') }}" data-testid="order-bar-home">
      <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant">
      <span class="logo-text"><strong>COOLAROO</strong><span>RESTAURANT &amp; BISTRO</span></span>
    </a>
    <span class="table-chip">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/><rect x="14" y="14" width="7" height="7" rx="1.3"/></svg>
      @yield('table_label')
    </span>
  </div>
</header>