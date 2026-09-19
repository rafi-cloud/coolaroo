<x-layouts.public title="Verify your email" description="Verify your email address to book a table at Coolaroo.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Verify your email</h1>
      <p>We sent a verification link to {{ auth('customer')->user()->email }}. Click it to unlock table reservations — you can keep ordering in the meantime.</p>
    </div>

    @if (session('status') === 'verification-link-sent')
      <div class="auth-error auth-success" role="status">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <span>A new verification link has been sent.</span>
      </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" novalidate>
      @csrf
      <button class="btn btn-orange" type="submit" data-testid="verify-email-resend">Resend verification email</button>
    </form>

    <p class="auth-foot"><a href="{{ url('/') }}" data-testid="verify-email-home-link">Back to homepage</a></p>
  </div>
</main>
</x-layouts.public>
