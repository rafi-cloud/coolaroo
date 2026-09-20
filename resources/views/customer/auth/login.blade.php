<x-layouts.public title="Log in" description="Log in to your Coolaroo account to view your bookings and order history.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Welcome back</h1>
      <p>Log in to view your bookings and past orders</p>
    </div>

    @if (! empty($qrTable))
      <div class="auth-error auth-success" role="status" data-testid="login-qr-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/></svg>
        <span>Log in to order at table {{ $qrTable->table_number }}.</span>
      </div>
    @endif

    @if (session('status'))
      <div class="auth-error auth-success" role="status">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <span>{{ session('status') }}</span>
      </div>
    @endif

    @error('email')
      <div class="auth-error" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span>{{ $message }}</span>
      </div>
    @enderror

    <form method="POST" action="{{ route('customer.login') }}" novalidate>
      @csrf

      <div class="auth-field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="{{ old('email') }}" required data-testid="login-email">
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="••••••••" required data-testid="login-password">
      </div>

      <div class="auth-row">
        <label><input type="checkbox" name="remember" data-testid="login-remember"> Remember me</label>
        <a href="{{ route('password.request') }}" data-testid="login-forgot-link">Forgot password?</a>
      </div>

      <button class="btn btn-orange" type="submit" data-testid="login-submit">Log in</button>
    </form>

    <p class="auth-foot">New to Coolaroo? <a href="{{ route('register') }}" data-testid="login-register-link">Create an account</a></p>

    @if (! empty($qrTable))
      <x-site.call-waiter :table="$qrTable" />
      <p class="auth-foot"><a href="{{ url('/') }}" data-testid="login-qr-home">Back to the homepage</a></p>
    @endif
  </div>
</main>
</x-layouts.public>
