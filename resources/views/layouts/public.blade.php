@include('layouts.partials.head', ['css' => 'style.css', 'suffix' => 'Coolaroo Restaurant & Bistro', 'vite' => false])
<body>

@include('layouts.partials.site-header')

<main id="top">
  @yield('content')
</main>

@include('layouts.partials.site-footer')

@stack('widgets')

<a class="totop" href="#top" aria-label="Back to top" data-testid="site-to-top">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 15l6-6 6 6"/></svg>
</a>

@stack('scripts')
</body>
</html>