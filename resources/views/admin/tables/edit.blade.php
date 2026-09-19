<x-layouts.admin title="Edit table" page-title="Edit table">
<div class="panel">
  <form method="POST" action="{{ route('admin.tables.update', $table) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="table_number">Table number</label>
      <input id="table_number" name="table_number" value="{{ old('table_number', $table->table_number) }}" required data-testid="admin-table-edit-number">
      @error('table_number')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="seat_capacity">Seats</label>
      <input id="seat_capacity" name="seat_capacity" type="number" min="1" value="{{ old('seat_capacity', $table->seat_capacity) }}" required data-testid="admin-table-edit-seats">
      @error('seat_capacity')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="section">Section</label>
      <input id="section" name="section" value="{{ old('section', $table->section) }}" data-testid="admin-table-edit-section">
      @error('section')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-table-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
