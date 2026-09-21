<x-layouts.customer title="My profile">
  <div class="wrap customer-container" data-testid="profile-page">
    <x-customer.nav-tabs active="profile" />

    <div class="customer-page-header">
      <div>
        <h1>My profile</h1>
        <p class="sub">Update your details or change your password</p>
      </div>
    </div>

    @if (session('status') === 'profile-updated')
      <div class="auth-error auth-success" role="status" style="margin-bottom: 1.5rem;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
        <span>Your profile has been updated.</span>
      </div>
    @endif

    <div class="panel" style="max-width: 600px; margin: 0 auto;">
      <form method="POST" action="{{ route('profile.update') }}" novalidate>
        @csrf
        @method('PATCH')

        <div class="auth-field">
          <label for="full_name">Full name</label>
          <input id="full_name" name="full_name" type="text" autocomplete="name" value="{{ old('full_name', $customer->full_name) }}" required data-testid="profile-name">
          @error('full_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $customer->email) }}" required data-testid="profile-email">
          @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
          <label for="phone">Phone number</label>
          <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $customer->phone) }}" data-testid="profile-phone">
          @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
          <label for="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password" data-testid="profile-current-password">
          <p class="field-error" style="color:var(--body);margin-top:.3rem">Only needed if you're setting a new password below.</p>
          @error('current_password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep your current password" minlength="8" data-testid="profile-password">
          @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
          <label for="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" data-testid="profile-password-confirmation">
        </div>

        <button class="btn btn-orange" type="submit" data-testid="profile-submit">Save changes</button>
      </form>
    </div>
  </div>
</x-layouts.customer>
