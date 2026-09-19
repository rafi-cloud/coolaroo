<x-layouts.admin title="Time slots" page-title="Time slots">
<div class="panel">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @error('slot')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  <a class="btn btn-solid" href="{{ route('admin.slots.create') }}" data-testid="admin-slot-add">Add time slot</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Max covers</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($slots as $slot)
          <tr>
            <td>{{ $slot->slot_time }}</td>
            <td>{{ $slot->max_covers }}</td>
            <td>
              @if ($slot->is_active)
                <span class="badge b-active">Active</span>
              @else
                <span class="badge b-inactive">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.slots.edit', $slot) }}" data-testid="admin-slot-edit-{{ $slot->slot_id }}">Edit</a>
              <form method="POST" action="{{ route('admin.slots.destroy', $slot) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost" data-testid="admin-slot-delete-{{ $slot->slot_id }}">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
