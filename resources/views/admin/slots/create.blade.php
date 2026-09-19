<x-layouts.admin title="Add time slot" page-title="Add time slot">
<div class="panel">
  <form method="POST" action="{{ route('admin.slots.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="slot_time">Slot time</label>
      <input id="slot_time" name="slot_time" type="time" value="{{ old('slot_time') }}" required data-testid="admin-slot-create-time">
      @error('slot_time')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="max_covers">Max covers</label>
      <input id="max_covers" name="max_covers" type="number" min="1" value="{{ old('max_covers') }}" required data-testid="admin-slot-create-covers">
      @error('max_covers')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-slot-create-submit">Create slot</button>
  </form>
</div>
</x-layouts.admin>
