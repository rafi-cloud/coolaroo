<x-layouts.public title="503 — Service Unavailable" description="Our system is temporarily undergoing maintenance or service is paused.">
<div class="auth">
  <div class="auth-card" role="region" aria-labelledby="error-503-title">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="" width="44" height="44">
      <h1 id="error-503-title">Temporarily Unavailable (503)</h1>
      <p>Our dining system is currently undergoing scheduled maintenance or the service is temporarily paused.</p>
    </div>

    <div class="auth-error" role="status" data-testid="error-503-message">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="10" y1="15" x2="10" y2="9"></line><line x1="14" y1="15" x2="14" y2="9"></line></svg>
      <span>{{ (isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'Please check back in a few moments.' }}</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem">
      <a class="btn btn-orange" href="{{ url('/') }}" data-testid="error-503-home">Back to Homepage</a>
    </div>

    <p class="auth-foot">Coolaroo Restaurant &amp; Bistro &middot; 412 Sydney Road, Coolaroo</p>
  </div>
</div>
</x-layouts.public>
