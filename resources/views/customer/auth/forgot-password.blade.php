<x-layouts.public title="Reset your password" description="Reset your Coolaroo account password.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Reset your password</h1>
      <p>Enter your account email and we'll send you a link to choose a new one.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
      @csrf

      <div class="auth-field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="{{ old('email') }}" required data-testid="forgot-password-email">
        @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <button class="btn btn-orange" type="submit" data-testid="forgot-password-submit">Send reset link</button>
    </form>

    @if (session('status'))
      <div class="auth-error auth-success" role="status">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <span>{{ session('status') }}</span>
      </div>
    @endif

    <p class="auth-foot">Remembered it after all? <a href="{{ route('customer.login') }}" data-testid="forgot-password-login-link">Back to login</a></p>
  </div>
</main>
</x-layouts.public>
