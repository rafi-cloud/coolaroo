<x-layouts.admin title="Edit dietary tag" page-title="Edit dietary tag">
<div class="panel">
  <form method="POST" action="{{ route('admin.dietary-tags.update', $tag) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="tag_name">Name</label>
      <input id="tag_name" name="tag_name" type="text" value="{{ old('tag_name', $tag->tag_name) }}" required data-testid="admin-tag-edit-name">
      @error('tag_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <input id="description" name="description" type="text" value="{{ old('description', $tag->description) }}" data-testid="admin-tag-edit-description">
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tag->is_active)) data-testid="admin-tag-edit-active"> Active</label>
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-tag-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
