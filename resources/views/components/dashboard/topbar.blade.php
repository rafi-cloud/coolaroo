@props([
    'title' => null,
    'sub' => null,
])
<header class="topbar">
  <label class="burger" for="nav-open">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    <span class="sr-only">Open navigation</span>
  </label>

  <div class="page-head">
    <h1>{{ $title ?? 'Coolaroo' }}</h1>
    <p>{{ $sub ?? now()->format('l, j F Y') }}</p>
  </div>

  <div class="topbar-tools">
    @auth('staff')
      <span class="profile">
        <span class="avatar" aria-hidden="true">{{ mb_substr(auth('staff')->user()->full_name, 0, 1) }}</span>
        <span class="profile-text"><strong>{{ auth('staff')->user()->full_name }}</strong></span>
      </span>
    @endauth
  </div>
</header>