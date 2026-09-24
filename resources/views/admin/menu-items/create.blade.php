<x-layouts.admin title="Add menu item" page-title="Add menu item">
<div class="panel">
  <form method="POST" action="{{ route('admin.menu-items.store') }}" enctype="multipart/form-data" novalidate>
    @csrf

    <div class="auth-field">
      <label for="item_name">Name</label>
      <input id="item_name" name="item_name" type="text" value="{{ old('item_name') }}" required data-testid="admin-menu-item-create-name">
      @error('item_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <textarea id="description" name="description" data-testid="admin-menu-item-create-description">{{ old('description') }}</textarea>
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="image">Photo</label>
      <input id="image" name="image" type="file" accept="image/*" required data-testid="admin-menu-item-create-image">
      @error('image')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required data-testid="admin-menu-item-create-category">
        @foreach ($categories as $category)
          <option value="{{ $category->category_id }}" @selected(old('category_id') == $category->category_id)>{{ $category->category_name }}</option>
        @endforeach
      </select>
      @error('category_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="destination">Station</label>
      <select id="destination" name="destination" required data-testid="admin-menu-item-create-destination">
        <option value="kitchen" @selected(old('destination') === 'kitchen')>Kitchen</option>
        <option value="bar" @selected(old('destination') === 'bar')>Bar</option>
      </select>
      @error('destination')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="prep_minutes">Prep time (minutes)</label>
      <input id="prep_minutes" name="prep_minutes" type="number" min="1" value="{{ old('prep_minutes', 10) }}" data-testid="admin-menu-item-create-prep">
      @error('prep_minutes')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <fieldset class="auth-field">
      <legend>Allergens</legend>
      @foreach ($allergens as $allergen)
        <label><input type="checkbox" name="allergens[]" value="{{ $allergen->allergen_id }}" data-testid="admin-menu-item-create-allergen-{{ $allergen->allergen_id }}"> {{ $allergen->allergen_name }}</label>
      @endforeach
    </fieldset>

    <fieldset class="auth-field">
      <legend>Dietary tags</legend>
      @foreach ($dietaryTags as $tag)
        <label><input type="checkbox" name="dietary_tags[]" value="{{ $tag->dietary_tag_id }}" data-testid="admin-menu-item-create-tag-{{ $tag->dietary_tag_id }}"> {{ $tag->tag_name }}</label>
      @endforeach
    </fieldset>

    <div class="auth-field">
      <label for="calories_kcal">Calories (kcal)</label>
      <input id="calories_kcal" name="calories_kcal" type="number" step="0.1" min="0" value="{{ old('calories_kcal') }}" data-testid="admin-menu-item-create-calories">
    </div>

    <div class="auth-field">
      <label for="protein_g">Protein (g)</label>
      <input id="protein_g" name="protein_g" type="number" step="0.1" min="0" value="{{ old('protein_g') }}" data-testid="admin-menu-item-create-protein">
    </div>

    <div class="auth-field">
      <label for="carbohydrates_g">Carbohydrates (g)</label>
      <input id="carbohydrates_g" name="carbohydrates_g" type="number" step="0.1" min="0" value="{{ old('carbohydrates_g') }}" data-testid="admin-menu-item-create-carbs">
    </div>

    <div class="auth-field">
      <label for="fat_g">Fat (g)</label>
      <input id="fat_g" name="fat_g" type="number" step="0.1" min="0" value="{{ old('fat_g') }}" data-testid="admin-menu-item-create-fat">
    </div>

    <div class="auth-field">
      <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured')) data-testid="admin-menu-item-create-featured"> Featured on homepage</label>
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-menu-item-create-submit">Create item</button>
  </form>

  <p class="field-error" style="color:var(--body)">Sizes and prices are added after saving.</p>
</div>
</x-layouts.admin>
