@include('layouts.partials.head', ['css' => 'dashboard.css', 'suffix' => 'Coolaroo Staff', 'vite' => true])
<body class="@yield('body_class')">

<input class="nav-state" type="checkbox" id="nav-open" data-testid="staff-nav-toggle">
<label class="scrim" for="nav-open" aria-hidden="true"></label>

<aside class="sidebar">
  <a class="brand" href="{{ url('/staff/floor') }}" data-testid="staff-home">
    <img src="{{ asset('images/logo.svg') }}" alt="">
    <span class="brand-text"><strong>COOLAROO</strong><span>STAFF</span></span>
  </a>
  @include('layouts.partials.nav-staff')
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