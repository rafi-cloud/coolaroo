@props([
    'title' => null,
    'pageTitle' => null,
    'pageSub' => null,
    'bodyClass' => null,
    'bare' => false,
])
@php
    $viewer = auth('staff')->user();
    $staffHome = $viewer?->role?->landingUrl() ?? '/staff/floor';
    $viewerIsAdmin = (bool) $viewer?->isAdmin();
@endphp
<x-document css="dashboard.css" :suffix="$viewerIsAdmin ? 'Coolaroo Admin' : 'Coolaroo Staff'" :vite="true" :title="$title" :body-class="$bodyClass">
@if ($bare)
{{ $slot }}
@else
<input class="nav-state" type="checkbox" id="nav-open" aria-label="Toggle navigation menu" data-testid="staff-nav-toggle">
<label class="scrim" for="nav-open" aria-hidden="true"></label>

<aside class="sidebar">
  <a class="brand" href="{{ $staffHome }}" data-testid="staff-home">
    <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant Logo">
    <span class="brand-text"><strong>COOLAROO</strong><span>{{ $viewerIsAdmin ? 'ADMIN' : 'STAFF' }}</span></span>
  </a>
  @if ($viewerIsAdmin)
    <x-dashboard.nav-admin />
  @else
    <x-dashboard.nav-staff />
  @endif
  <x-dashboard.nav-logout />
</aside>

<div class="shell">
  <x-dashboard.topbar :title="$pageTitle" :sub="$pageSub" />

  <main class="content">
    {{ $slot }}
    <x-dashboard.footer />
  </main>
</div>
@endif
</x-document>
