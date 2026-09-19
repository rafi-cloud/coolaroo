<x-layouts.admin title="Edit staff account" page-title="Edit staff account">
<div class="panel">
  <form method="POST" action="{{ route('admin.staff.update', $staff) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $staff->full_name) }}" required data-testid="admin-staff-edit-name">
      @error('full_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" value="{{ old('email', $staff->email) }}" required data-testid="admin-staff-edit-email">
      @error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="phone">Phone number</label>
      <input id="phone" name="phone" type="tel" value="{{ old('phone', $staff->phone) }}" data-testid="admin-staff-edit-phone">
      @error('phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="role_id">Role</label>
      <select id="role_id" name="role_id" required data-testid="admin-staff-edit-role">
        @foreach ($roles as $role)
          <option value="{{ $role->role_id }}" @selected(old('role_id', $staff->role_id) == $role->role_id)>{{ ucfirst($role->role_name) }}</option>
        @endforeach
      </select>
      @error('role_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-staff-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
