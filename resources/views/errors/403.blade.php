<x-layouts.public title="403 — Access Forbidden" description="You do not have permission to access this resource.">
<div class="auth">
  <div class="auth-card" role="region" aria-labelledby="error-403-title">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="" width="44" height="44">
      <h1 id="error-403-title">Access Forbidden (403)</h1>
      <p>You don't have permission to access this page, or your table session has expired.</p>
    </div>

    <div class="auth-error" role="alert" data-testid="error-403-message">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <span>{{ (isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'Access restricted. If you scanned a table QR code, please scan the code on your table again.' }}</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem">
      <a class="btn btn-orange" href="{{ url('/') }}" data-testid="error-403-home">Back to Homepage</a>
      <a class="btn btn-outline" href="{{ route('menu.index') }}" data-testid="error-403-menu">Browse Menu</a>
    </div>

    <p class="auth-foot">If you are dining with us, our waitstaff will be happy to assist you in person.</p>
  </div>
</div>
</x-layouts.public>
