<x-layouts.public title="Choose a new password" description="Choose a new password for your Coolaroo account.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="Coolaroo Restaurant Logo">
      <h1>Choose a new password</h1>
      <p>Enter a new password for your account</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">

      <div class="auth-field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $email) }}" required data-testid="reset-password-email">
        @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required minlength="8" data-testid="reset-password-password">
        @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" data-testid="reset-password-password-confirmation">
      </div>

      <button class="btn btn-orange" type="submit" data-testid="reset-password-submit">Reset password</button>
    </form>
  </div>
</main>
</x-layouts.public>
