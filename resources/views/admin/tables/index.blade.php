<x-layouts.admin title="Tables" page-title="Tables">
<div class="panel">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @if (session('warning'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('warning') }}</span>
    </div>
  @endif

  <a class="btn btn-solid" href="{{ route('admin.tables.create') }}" data-testid="admin-table-add">Add table</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Number</th>
          <th>Section</th>
          <th>Seats</th>
          <th>Status</th>
          <th>Active</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($tables as $table)
          <tr>
            <td>{{ $table->table_number }}</td>
            <td>{{ $table->section }}</td>
            <td>{{ $table->seat_capacity }}</td>
            <td>
              <form method="POST" action="{{ route('admin.tables.status', $table) }}" style="display:inline-flex;gap:.4rem;align-items:center">
                @csrf
                @method('PATCH')
                <select name="status" data-testid="admin-table-status-select-{{ $table->table_id }}">
                  <option value="available" @selected($table->status->value === 'available')>Available</option>
                  <option value="reserved" @selected($table->status->value === 'reserved')>Reserved</option>
                  <option value="occupied" @selected($table->status->value === 'occupied')>Occupied</option>
                </select>
                <input name="reason" placeholder="Reason" required data-testid="admin-table-status-reason-{{ $table->table_id }}">
                <button type="submit" class="btn btn-ghost" data-testid="admin-table-status-submit-{{ $table->table_id }}">Override</button>
              </form>
            </td>
            <td>
              @if ($table->is_active)
                <span class="badge b-active">Active</span>
              @else
                <span class="badge b-inactive">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.tables.edit', $table) }}" data-testid="admin-table-edit-{{ $table->table_id }}">Edit</a>

              @if ($table->is_active)
                <form method="POST" action="{{ route('admin.tables.deactivate', $table) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-ghost" data-testid="admin-table-deactivate-{{ $table->table_id }}">Deactivate</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.tables.reactivate', $table) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-table-reactivate-{{ $table->table_id }}">Reactivate</button>
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
