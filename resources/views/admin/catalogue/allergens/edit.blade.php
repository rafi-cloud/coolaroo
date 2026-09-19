<x-layouts.admin title="Edit allergen" page-title="Edit allergen">
<div class="panel">
  <form method="POST" action="{{ route('admin.allergens.update', $allergen) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="allergen_name">Name</label>
      <input id="allergen_name" name="allergen_name" type="text" value="{{ old('allergen_name', $allergen->allergen_name) }}" required data-testid="admin-allergen-edit-name">
      @error('allergen_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <input id="description" name="description" type="text" value="{{ old('description', $allergen->description) }}" data-testid="admin-allergen-edit-description">
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $allergen->is_active)) data-testid="admin-allergen-edit-active"> Active</label>
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-allergen-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
