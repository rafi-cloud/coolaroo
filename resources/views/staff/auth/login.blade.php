<x-layouts.staff title="Staff login" :bare="true">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Staff sign in</h1>
      <p>Sign in with your Coolaroo staff account</p>
    </div>

    @error('email')
      <div class="auth-error" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span>{{ $message }}</span>
      </div>
    @enderror

    @if (session('status'))
      <div class="auth-error auth-success" role="status">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <span>{{ session('status') }}</span>
      </div>
    @endif

    <form method="POST" action="{{ route('staff.login') }}" novalidate>
      @csrf

      <div class="auth-field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required data-testid="staff-login-email">
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required data-testid="staff-login-password">
      </div>

      <button class="btn btn-solid" type="submit" data-testid="staff-login-submit">Sign in</button>
    </form>

    <p class="auth-foot">Forgotten your password? Ask an administrator to reset it.</p>
  </div>
</main>
</x-layouts.staff>
