<x-layouts.public title="Create an account" description="Create a Coolaroo account to book tables faster and track your order history.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Create your account</h1>
      <p>Book tables faster and keep track of your orders</p>
    </div>

    <form method="POST" action="{{ route('register') }}" novalidate>
      @csrf

      <div class="auth-field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" autocomplete="name" value="{{ old('full_name') }}" required data-testid="register-name">
        @error('full_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="{{ old('email') }}" required data-testid="register-email">
        @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="04xx xxx xxx" value="{{ old('phone') }}" required data-testid="register-phone">
        @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required minlength="8" data-testid="register-password">
        @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      </div>

      <div class="auth-field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" data-testid="register-password-confirmation">
      </div>

      <button class="btn btn-orange" type="submit" data-testid="register-submit">Create account</button>
    </form>

    <p class="auth-foot">Already have an account? <a href="{{ route('customer.login') }}" data-testid="register-login-link">Log in</a></p>
  </div>
</main>
</x-layouts.public>
