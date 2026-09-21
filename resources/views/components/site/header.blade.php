<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="logo" href="{{ url('/') }}" data-testid="site-home">
      <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant">
      <span class="logo-text"><strong>COOLAROO</strong><span>RESTAURANT &amp; BISTRO</span></span>
    </a>

    <input class="nav-toggle" id="nav-toggle" type="checkbox" aria-label="Toggle navigation menu" data-testid="site-nav-toggle">
    <label class="hamburger" for="nav-toggle">
      <span class="visually-hidden">Open menu</span>
      <span class="bar"></span><span class="bar"></span><span class="bar"></span>
    </label>

    <nav class="mainnav" aria-label="Main">
      <a href="{{ url('/') }}" data-testid="site-nav-home">Home</a>
      <a href="{{ url('/menu') }}" data-testid="site-nav-menu">Menu</a>
      @if(app(\App\Services\SettingService::class)->getBool('ai_enabled', true))
        <a href="{{ route('meal-builder') }}" class="nav-ai" data-testid="site-nav-meal-builder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:14px;height:14px;vertical-align:-1px;margin-right:4px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>Build a Meal
        </a>
      @endif
      <a href="{{ url('/#reviews') }}" data-testid="site-nav-reviews">Reviews</a>
      <a href="{{ url('/#about') }}" data-testid="site-nav-about">About</a>
      @auth('customer')
        <a href="{{ url('/profile') }}" data-testid="site-nav-account">My account</a>
      @else
        <a href="{{ url('/login') }}" data-testid="site-nav-sign-in">Sign in</a>
      @endauth
      <a class="btn btn-orange" href="{{ url('/#reserve') }}" data-testid="site-book-table">Book a table</a>
    </nav>
  </div>
</header>