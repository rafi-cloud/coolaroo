@props([
    'title' => null,
    'pageTitle' => null,
    'pageSub' => null,
    'bodyClass' => null,
    'bare' => false,
])
@php
    $staffHome = '/staff/floor';

    if (auth('staff')->check()) {
        $landingScreen = auth('staff')->user()->role->landing_screen;

        if (\Illuminate\Support\Facades\Route::has($landingScreen)) {
            $staffHome = route($landingScreen);
        }
    }
@endphp
<x-document css="dashboard.css" suffix="Coolaroo Staff" :vite="true" :title="$title" :body-class="$bodyClass">
@if ($bare)
{{ $slot }}
@else
<input class="nav-state" type="checkbox" id="nav-open" data-testid="staff-nav-toggle">
<label class="scrim" for="nav-open" aria-hidden="true"></label>

<aside class="sidebar">
  <a class="brand" href="{{ $staffHome }}" data-testid="staff-home">
    <img src="{{ asset('images/logo.svg') }}" alt="">
    <span class="brand-text"><strong>COOLAROO</strong><span>STAFF</span></span>
  </a>
  <x-dashboard.nav-staff />
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
