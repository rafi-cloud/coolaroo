<x-layouts.public title="404 — Page Not Found" description="The page you were looking for could not be found.">
<div class="auth">
  <div class="auth-card" role="region" aria-labelledby="error-404-title">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="" width="44" height="44">
      <h1 id="error-404-title">Page Not Found (404)</h1>
      <p>We couldn't find the page or dish you're looking for. It may have been moved, renamed, or is temporarily unavailable.</p>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem">
      <a class="btn btn-orange" href="{{ url('/') }}" data-testid="error-404-home">Back to Homepage</a>
      <a class="btn btn-outline" href="{{ route('menu.index') }}" data-testid="error-404-menu">View Our Menu</a>
    </div>

    <p class="auth-foot"><a href="{{ url('/#reserve') }}" data-testid="error-404-reserve">Book a table online</a></p>
  </div>
</div>
</x-layouts.public>
