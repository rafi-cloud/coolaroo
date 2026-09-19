<x-layouts.admin title="Add allergen" page-title="Add allergen">
<div class="panel">
  <form method="POST" action="{{ route('admin.allergens.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="allergen_name">Name</label>
      <input id="allergen_name" name="allergen_name" type="text" value="{{ old('allergen_name') }}" required data-testid="admin-allergen-create-name">
      @error('allergen_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <input id="description" name="description" type="text" value="{{ old('description') }}" data-testid="admin-allergen-create-description">
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-allergen-create-submit">Create allergen</button>
  </form>
</div>
</x-layouts.admin>
