<x-layouts.admin title="Add staff account" page-title="Add staff account">
<div class="panel">
  <form method="POST" action="{{ route('admin.staff.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" required data-testid="admin-staff-create-name">
      @error('full_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" required data-testid="admin-staff-create-email">
      @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="phone">Phone number</label>
      <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" data-testid="admin-staff-create-phone">
      @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="role_id">Role</label>
      <select id="role_id" name="role_id" required data-testid="admin-staff-create-role">
        <option value="">Choose a role</option>
        @foreach ($roles as $role)
          <option value="{{ $role->role_id }}" @selected(old('role_id') == $role->role_id)>{{ ucfirst($role->role_name) }}</option>
        @endforeach
      </select>
      @error('role_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="password">Temporary password</label>
      <input id="password" name="password" type="password" minlength="8" required aria-describedby="password-hint" data-testid="admin-staff-create-password">
      <p class="form-hint" id="password-hint">At least 8 characters, with upper and lower case, a number and a symbol.</p>
      @error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="password_confirmation">Confirm password</label>
      <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required data-testid="admin-staff-create-password-confirmation">
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-staff-create-submit">Create account</button>
  </form>
</div>
</x-layouts.admin>
