<x-layouts.admin title="Staff accounts" page-title="Staff accounts">
<div class="panel">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>
        @switch(session('status'))
          @case('staff-created') Staff account created. @break
          @case('staff-updated') Staff account updated. @break
          @case('staff-deactivated') Staff account deactivated. @break
          @case('staff-reactivated') Staff account reactivated. @break
        @endswitch
      </span>
    </div>
  @endif

  @error('staff')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  <a class="btn btn-solid" href="{{ route('admin.staff.create') }}" data-testid="admin-staff-add">Add staff account</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($staff as $member)
          <tr>
            <td>{{ $member->full_name }}</td>
            <td>{{ $member->email }}</td>
            <td>{{ ucfirst($member->role->role_name) }}</td>
            <td>
              @if ($member->is_active)
                <span class="badge b-active" data-testid="admin-staff-status-{{ $member->staff_id }}">Active</span>
              @else
                <span class="badge b-inactive" data-testid="admin-staff-status-{{ $member->staff_id }}">Deactivated</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.staff.edit', $member) }}" data-testid="admin-staff-edit-{{ $member->staff_id }}">Edit</a>

              @if ($member->is_active)
                <form method="POST" action="{{ route('admin.staff.deactivate', $member) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-ghost" data-testid="admin-staff-deactivate-{{ $member->staff_id }}">Deactivate</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.staff.reactivate', $member) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-staff-reactivate-{{ $member->staff_id }}">Reactivate</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
