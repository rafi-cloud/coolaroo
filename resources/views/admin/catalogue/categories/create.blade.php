<x-layouts.admin title="Add category" page-title="Add category">
<div class="panel">
  <form method="POST" action="{{ route('admin.categories.store') }}" novalidate>
    @csrf

    <div class="auth-field">
      <label for="category_name">Name</label>
      <input id="category_name" name="category_name" type="text" value="{{ old('category_name') }}" required data-testid="admin-category-create-name">
      @error('category_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="display_order">Display order</label>
      <input id="display_order" name="display_order" type="number" min="0" value="{{ old('display_order', 0) }}" data-testid="admin-category-create-order">
      @error('display_order')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="parent_category_id">Parent category</label>
      <select id="parent_category_id" name="parent_category_id" data-testid="admin-category-create-parent">
        <option value="">None (top level)</option>
        @foreach ($topLevelCategories as $topLevel)
          <option value="{{ $topLevel->category_id }}" @selected(old('parent_category_id') == $topLevel->category_id)>{{ $topLevel->category_name }}</option>
        @endforeach
      </select>
      @error('parent_category_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-category-create-submit">Create category</button>
  </form>
</div>
</x-layouts.admin>
