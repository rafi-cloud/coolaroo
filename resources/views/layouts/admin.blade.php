@include('layouts.partials.head', ['css' => 'dashboard.css', 'suffix' => 'Coolaroo Admin', 'vite' => true])
<body>

<input class="nav-state" type="checkbox" id="nav-open" data-testid="admin-nav-toggle">
<label class="scrim" for="nav-open" aria-hidden="true"></label>

<aside class="sidebar">
  <a class="brand" href="{{ url('/admin') }}" data-testid="admin-home">
    <img src="{{ asset('images/logo.svg') }}" alt="">
    <span class="brand-text"><strong>COOLAROO</strong><span>ADMIN</span></span>
  </a>
  @include('layouts.partials.nav-admin')
</aside>

<div class="shell">
  @include('layouts.partials.dashboard-topbar')

  <main class="content">
    @yield('content')
    @include('layouts.partials.dashboard-footer')
  </main>
</div>

@stack('scripts')
</body>
</html>