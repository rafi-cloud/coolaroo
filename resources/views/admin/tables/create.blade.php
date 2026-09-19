<x-layouts.admin title="Add table" page-title="Add table">
<div class="panel">
  <form method="POST" action="{{ route('admin.tables.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="table_number">Table number</label>
      <input id="table_number" name="table_number" value="{{ old('table_number') }}" required data-testid="admin-table-create-number">
      @error('table_number')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="seat_capacity">Seats</label>
      <input id="seat_capacity" name="seat_capacity" type="number" min="1" value="{{ old('seat_capacity') }}" required data-testid="admin-table-create-seats">
      @error('seat_capacity')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="section">Section</label>
      <input id="section" name="section" value="{{ old('section') }}" data-testid="admin-table-create-section">
      @error('section')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-table-create-submit">Create table</button>
  </form>
</div>
</x-layouts.admin>
