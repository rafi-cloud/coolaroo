<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="logo" href="{{ url('/') }}" data-testid="site-home">
      <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant">
      <span class="logo-text"><strong>COOLAROO</strong><span>RESTAURANT &amp; BISTRO</span></span>
    </a>

    <input class="nav-toggle" id="nav-toggle" type="checkbox" data-testid="site-nav-toggle">
    <label class="hamburger" for="nav-toggle">
      <span class="visually-hidden">Open menu</span>
      <span class="bar"></span><span class="bar"></span><span class="bar"></span>
    </label>

    <nav class="mainnav" aria-label="Main">
      <a href="{{ url('/') }}" data-testid="site-nav-home">Home</a>
      <a href="{{ url('/menu') }}" data-testid="site-nav-menu">Menu</a>
      <a href="{{ url('/#reviews') }}" data-testid="site-nav-reviews">Reviews</a>
      <a href="{{ url('/#about') }}" data-testid="site-nav-about">About</a>
      @auth('customer')
        <a href="{{ url('/profile') }}" data-testid="site-nav-account">My account</a>
      @else
        <a href="{{ url('/login') }}" data-testid="site-nav-sign-in">Sign in</a>
      @endauth
      <a class="btn btn-orange" href="{{ url('/#reserve') }}" data-testid="site-book-table">Book a table</a>
      <span class="icons">
        <a href="{{ url('/menu') }}" aria-label="Search the menu" data-testid="site-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
        </a>
      </span>
    </nav>
  </div>
</header>