<x-layouts.public title="500 — Server Error" description="Something went wrong on our end. Please try again shortly.">
<div class="auth">
  <div class="auth-card" role="region" aria-labelledby="error-500-title">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="" width="44" height="44">
      <h1 id="error-500-title">Something Went Wrong (500)</h1>
      <p>Our server encountered an unexpected error. Our technical team has been notified.</p>
    </div>

    <div class="auth-error" role="alert" data-testid="error-500-message">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <span>Please refresh the page or try again in a few moments.</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-top:1.5rem">
      <a class="btn btn-orange" href="{{ url('/') }}" data-testid="error-500-home">Back to Homepage</a>
      <a class="btn btn-outline" href="{{ route('menu.index') }}" data-testid="error-500-menu">Browse Menu</a>
    </div>

    <p class="auth-foot">If you are at the venue, our waitstaff will be delighted to assist you directly.</p>
  </div>
</div>
</x-layouts.public>
