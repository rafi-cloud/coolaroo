<x-layouts.staff title="My profile" page-title="My profile">
<div class="panel">
  @if (session('status') === 'profile-updated')
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Your profile has been updated.</span>
    </div>
  @endif

  <form method="POST" action="{{ route('staff.profile.update') }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" type="text" autocomplete="name" value="{{ old('full_name', $staff->full_name) }}" required data-testid="staff-profile-name">
      @error('full_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="phone">Phone number</label>
      <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $staff->phone) }}" data-testid="staff-profile-phone">
      @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password" autocomplete="current-password" data-testid="staff-profile-current-password">
      @error('current_password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="password">New password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep your current password" minlength="8" data-testid="staff-profile-password">
      @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="password_confirmation">Confirm new password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" data-testid="staff-profile-password-confirmation">
    </div>

    <button class="btn btn-solid" type="submit" data-testid="staff-profile-submit">Save changes</button>
  </form>
</div>
</x-layouts.staff>
