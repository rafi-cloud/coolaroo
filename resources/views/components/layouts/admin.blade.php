@props([
    'title' => null,
    'pageTitle' => null,
    'pageSub' => null,
])
<x-document css="dashboard.css" suffix="Coolaroo Admin" :vite="true" :title="$title">
<input class="nav-state" type="checkbox" id="nav-open" data-testid="admin-nav-toggle">
<label class="scrim" for="nav-open" aria-hidden="true"></label>

<aside class="sidebar">
  <a class="brand" href="{{ url('/admin') }}" data-testid="admin-home">
    <img src="{{ asset('images/logo.svg') }}" alt="">
    <span class="brand-text"><strong>COOLAROO</strong><span>ADMIN</span></span>
  </a>
  <x-dashboard.nav-admin />
</aside>

<div class="shell">
  <x-dashboard.topbar :title="$pageTitle" :sub="$pageSub" />

  <main class="content">
    {{ $slot }}
    <x-dashboard.footer />
  </main>
</div>
</x-document>
