<x-layouts.admin title="Add dietary tag" page-title="Add dietary tag">
<div class="panel">
  <form method="POST" action="{{ route('admin.dietary-tags.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="tag_name">Name</label>
      <input id="tag_name" name="tag_name" type="text" value="{{ old('tag_name') }}" required data-testid="admin-tag-create-name">
      @error('tag_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <input id="description" name="description" type="text" value="{{ old('description') }}" data-testid="admin-tag-create-description">
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-tag-create-submit">Create dietary tag</button>
  </form>
</div>
</x-layouts.admin>
