<x-layouts.admin title="Edit category" page-title="Edit category">
<div class="panel">
  <form method="POST" action="{{ route('admin.categories.update', $category) }}" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="category_name">Name</label>
      <input id="category_name" name="category_name" type="text" value="{{ old('category_name', $category->category_name) }}" required data-testid="admin-category-edit-name">
      @error('category_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="display_order">Display order</label>
      <input id="display_order" name="display_order" type="number" min="0" value="{{ old('display_order', $category->display_order) }}" data-testid="admin-category-edit-order">
      @error('display_order')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="parent_category_id">Parent category</label>
      <select id="parent_category_id" name="parent_category_id" data-testid="admin-category-edit-parent">
        <option value="">None (top level)</option>
        @foreach ($topLevelCategories as $topLevel)
          <option value="{{ $topLevel->category_id }}" @selected(old('parent_category_id', $category->parent_category_id) == $topLevel->category_id)>{{ $topLevel->category_name }}</option>
        @endforeach
      </select>
      @error('parent_category_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-category-edit-submit">Save changes</button>
  </form>
</div>
</x-layouts.admin>
