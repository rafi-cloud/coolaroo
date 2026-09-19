@include('layouts.partials.head', ['css' => 'style.css', 'suffix' => 'Coolaroo Restaurant & Bistro', 'vite' => true])
<body>

@hasSection('table_label')
  @include('layouts.partials.order-bar')
@else
  @include('layouts.partials.site-header')
@endif

<main id="top">
  @yield('content')
</main>

@stack('scripts')
</body>
</html>