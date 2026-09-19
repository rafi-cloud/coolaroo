<x-layouts.admin title="Edit time slot" page-title="Edit time slot">
<div class="panel">
  <form method="POST" action="{{ route('admin.slots.update', $slot) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="slot_time">Slot time</label>
      <input id="slot_time" name="slot_time" type="time" value="{{ old('slot_time', $slot->slot_time) }}" required data-testid="admin-slot-edit-time">
      @error('slot_time')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="max_covers">Max covers</label>
      <input id="max_covers" name="max_covers" type="number" min="1" value="{{ old('max_covers', $slot->max_covers) }}" required data-testid="admin-slot-edit-covers">
      @error('max_covers')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $slot->is_active)) data-testid="admin-slot-edit-active"> Bookable online</label>
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-slot-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
