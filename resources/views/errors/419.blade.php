<x-layouts.public title="419 — Page Expired" description="Your session has expired. Please refresh the page and try again.">
<div class="auth">
  <div class="auth-card" role="region" aria-labelledby="error-419-title">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="" width="44" height="44">
      <h1 id="error-419-title">Session Expired (419)</h1>
      <p>Your security token or form session expired due to inactivity. Please refresh the page to continue.</p>
    </div>

    <div class="auth-error" role="status" data-testid="error-419-message">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      <span>To protect your security, form submissions expire after a period of inactivity.</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem">
      <a class="btn btn-orange" href="javascript:window.location.reload();" data-testid="error-419-refresh">Refresh Page</a>
      <a class="btn btn-outline" href="{{ url('/') }}" data-testid="error-419-home">Return to Homepage</a>
    </div>

    <p class="auth-foot"><a href="{{ route('customer.login') }}" data-testid="error-419-login">Sign in to your account</a></p>
  </div>
</div>
</x-layouts.public>
