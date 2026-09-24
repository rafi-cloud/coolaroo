<x-layouts.admin title="Edit menu item" page-title="Edit menu item">
<div class="panel">
  <form method="POST" action="{{ route('admin.menu-items.update', $item) }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PATCH')

    <div class="auth-field">
      <label for="item_name">Name</label>
      <input id="item_name" name="item_name" type="text" value="{{ old('item_name', $item->item_name) }}" required data-testid="admin-menu-item-edit-name">
      @error('item_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="description">Description</label>
      <textarea id="description" name="description" data-testid="admin-menu-item-edit-description">{{ old('description', $item->description) }}</textarea>
      @error('description')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      @if ($item->image_path)
        <img src="{{ asset('storage/'.$item->image_path) }}" alt="" style="max-width:160px;border-radius:8px;margin-bottom:.6rem">
      @endif
      <label for="image">Replace photo</label>
      <input id="image" name="image" type="file" accept="image/*" data-testid="admin-menu-item-edit-image">
      @error('image')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required data-testid="admin-menu-item-edit-category">
        @foreach ($categories as $category)
          <option value="{{ $category->category_id }}" @selected(old('category_id', $item->category_id) == $category->category_id)>{{ $category->category_name }}</option>
        @endforeach
      </select>
      @error('category_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="destination">Station</label>
      <select id="destination" name="destination" required data-testid="admin-menu-item-edit-destination">
        <option value="kitchen" @selected(old('destination', $item->destination->value) === 'kitchen')>Kitchen</option>
        <option value="bar" @selected(old('destination', $item->destination->value) === 'bar')>Bar</option>
      </select>
      @error('destination')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="prep_minutes">Prep time (minutes)</label>
      <input id="prep_minutes" name="prep_minutes" type="number" min="1" value="{{ old('prep_minutes', $item->prep_minutes) }}" data-testid="admin-menu-item-edit-prep">
      @error('prep_minutes')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <fieldset class="auth-field">
      <legend>Allergens</legend>
      @foreach ($allergens as $allergen)
        <label><input type="checkbox" name="allergens[]" value="{{ $allergen->allergen_id }}" @checked(in_array($allergen->allergen_id, old('allergens', $selectedAllergens))) data-testid="admin-menu-item-edit-allergen-{{ $allergen->allergen_id }}"> {{ $allergen->allergen_name }}</label>
      @endforeach
    </fieldset>

    <fieldset class="auth-field">
      <legend>Dietary tags</legend>
      @foreach ($dietaryTags as $tag)
        <label><input type="checkbox" name="dietary_tags[]" value="{{ $tag->dietary_tag_id }}" @checked(in_array($tag->dietary_tag_id, old('dietary_tags', $selectedDietaryTags))) data-testid="admin-menu-item-edit-tag-{{ $tag->dietary_tag_id }}"> {{ $tag->tag_name }}</label>
      @endforeach
    </fieldset>

    <div class="auth-field">
      <label for="calories_kcal">Calories (kcal)</label>
      <input id="calories_kcal" name="calories_kcal" type="number" step="0.1" min="0" value="{{ old('calories_kcal', $item->calories_kcal) }}" data-testid="admin-menu-item-edit-calories">
    </div>

    <div class="auth-field">
      <label for="protein_g">Protein (g)</label>
      <input id="protein_g" name="protein_g" type="number" step="0.1" min="0" value="{{ old('protein_g', $item->protein_g) }}" data-testid="admin-menu-item-edit-protein">
    </div>

    <div class="auth-field">
      <label for="carbohydrates_g">Carbohydrates (g)</label>
      <input id="carbohydrates_g" name="carbohydrates_g" type="number" step="0.1" min="0" value="{{ old('carbohydrates_g', $item->carbohydrates_g) }}" data-testid="admin-menu-item-edit-carbs">
    </div>

    <div class="auth-field">
      <label for="fat_g">Fat (g)</label>
      <input id="fat_g" name="fat_g" type="number" step="0.1" min="0" value="{{ old('fat_g', $item->fat_g) }}" data-testid="admin-menu-item-edit-fat">
    </div>

    <div class="auth-field">
      <label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured)) data-testid="admin-menu-item-edit-featured"> Featured on homepage</label>
    </div>

    <button class="btn btn-solid" type="submit" data-testid="admin-menu-item-edit-submit">Save changes</button>
  </form>
</div>

<div class="panel">
  <h2>Sizes</h2>

  @error('size')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  @foreach ($sizes as $size)
    <form method="POST" action="{{ route('admin.menu-items.sizes.update', [$item, $size]) }}" style="margin-bottom:1rem">
      @csrf
      @method('PATCH')
      <div class="auth-field">
        <label for="size_name_{{ $size->size_id }}">Name</label>
        <input id="size_name_{{ $size->size_id }}" name="size_name" value="{{ $size->size_name }}" required data-testid="admin-size-name-{{ $size->size_id }}">
      </div>
      <div class="auth-field">
        <label for="price_{{ $size->size_id }}">Price (AUD, GST-inclusive)</label>
        <input id="price_{{ $size->size_id }}" name="price" type="number" step="0.01" min="0.01" value="{{ $size->price }}" required data-testid="admin-size-price-{{ $size->size_id }}">
      </div>
      <div class="auth-field">
        <label for="sale_price_{{ $size->size_id }}">Sale price (optional)</label>
        <input id="sale_price_{{ $size->size_id }}" name="sale_price" type="number" step="0.01" min="0.01" value="{{ $size->sale_price }}" data-testid="admin-size-sale-price-{{ $size->size_id }}">
      </div>
      <div class="auth-field">
        <label for="sale_starts_at_{{ $size->size_id }}">Sale starts</label>
        <input id="sale_starts_at_{{ $size->size_id }}" name="sale_starts_at" type="datetime-local" value="{{ optional($size->sale_starts_at)->format('Y-m-d\TH:i') }}">
      </div>
      <div class="auth-field">
        <label for="sale_ends_at_{{ $size->size_id }}">Sale ends</label>
        <input id="sale_ends_at_{{ $size->size_id }}" name="sale_ends_at" type="datetime-local" value="{{ optional($size->sale_ends_at)->format('Y-m-d\TH:i') }}">
      </div>
      <button class="btn btn-solid" type="submit" data-testid="admin-size-save-{{ $size->size_id }}">Save size</button>
    </form>
    <form method="POST" action="{{ route('admin.menu-items.sizes.destroy', [$item, $size]) }}" style="display:inline">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-ghost" data-testid="admin-size-delete-{{ $size->size_id }}">Delete size</button>
    </form>
  @endforeach

  <h3>Add a size</h3>
  <form method="POST" action="{{ route('admin.menu-items.sizes.store', $item) }}">
    @csrf
    <div class="auth-field">
      <label for="new_size_name">Name</label>
      <input id="new_size_name" name="size_name" value="Regular" required data-testid="admin-size-create-name">
    </div>
    <div class="auth-field">
      <label for="new_price">Price (AUD, GST-inclusive)</label>
      <input id="new_price" name="price" type="number" step="0.01" min="0.01" required data-testid="admin-size-create-price">
    </div>
    <button class="btn btn-solid" type="submit" data-testid="admin-size-create-submit">Add size</button>
  </form>
</div>

<div class="panel">
  <h2>Add-on groups</h2>

  <p class="form-hint">Min and max are how many options a customer must and may choose from the group. Max must be at least min.</p>

  @foreach ($groups as $group)
    <div class="addon-group">
      <div class="addon-group-head">
        <h3>{{ $group->group_name }}</h3>
        <form method="POST" action="{{ route('admin.menu-items.groups.destroy', [$item, $group]) }}">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-ghost" data-testid="admin-group-delete-{{ $group->group_id }}">Delete group</button>
        </form>
      </div>

      <div class="addon-row">
        <form class="addon-form" method="POST" action="{{ route('admin.menu-items.groups.update', [$item, $group]) }}">
          @csrf
          @method('PATCH')
          <div class="addon-field">
            <label for="group_name_{{ $group->group_id }}">Group name</label>
            <input id="group_name_{{ $group->group_id }}" name="group_name" value="{{ $group->group_name }}" required data-testid="admin-group-name-{{ $group->group_id }}">
          </div>
          <div class="addon-field addon-field--narrow">
            <label for="min_select_{{ $group->group_id }}">Min select</label>
            <input id="min_select_{{ $group->group_id }}" name="min_select" type="number" min="0" value="{{ $group->min_select }}" data-testid="admin-group-min-{{ $group->group_id }}">
          </div>
          <div class="addon-field addon-field--narrow">
            <label for="max_select_{{ $group->group_id }}">Max select</label>
            <input id="max_select_{{ $group->group_id }}" name="max_select" type="number" min="0" value="{{ $group->max_select }}" data-testid="admin-group-max-{{ $group->group_id }}">
          </div>
          <label class="addon-check" for="is_required_{{ $group->group_id }}">
            <input id="is_required_{{ $group->group_id }}" type="checkbox" name="is_required" value="1" @checked($group->is_required)> Required
          </label>
          <button class="btn btn-solid" type="submit" data-testid="admin-group-save-{{ $group->group_id }}">Save group</button>
        </form>
      </div>

      <p class="addon-options-title">Options</p>

      @foreach ($group->options as $option)
        <div class="addon-row">
          <form class="addon-form" method="POST" action="{{ route('admin.menu-items.groups.options.update', [$item, $group, $option]) }}">
            @csrf
            @method('PATCH')
            <div class="addon-field">
              <label for="option_name_{{ $option->option_id }}">Option name</label>
              <input id="option_name_{{ $option->option_id }}" name="option_name" value="{{ $option->option_name }}" required data-testid="admin-option-name-{{ $option->option_id }}">
            </div>
            <div class="addon-field addon-field--narrow">
              <label for="price_delta_{{ $option->option_id }}">Extra price ($)</label>
              <input id="price_delta_{{ $option->option_id }}" name="price_delta" type="number" step="0.01" min="0" value="{{ $option->price_delta }}" data-testid="admin-option-price-{{ $option->option_id }}">
            </div>
            <label class="addon-check" for="is_available_{{ $option->option_id }}">
              <input id="is_available_{{ $option->option_id }}" type="checkbox" name="is_available" value="1" @checked($option->is_available)> Available
            </label>
            <button class="btn btn-solid" type="submit" data-testid="admin-option-save-{{ $option->option_id }}">Save</button>
          </form>
          <form method="POST" action="{{ route('admin.menu-items.groups.options.destroy', [$item, $group, $option]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost" data-testid="admin-option-delete-{{ $option->option_id }}">Delete</button>
          </form>
        </div>
      @endforeach

      <div class="addon-row addon-add">
        <form class="addon-form" method="POST" action="{{ route('admin.menu-items.groups.options.store', [$item, $group]) }}">
          @csrf
          <div class="addon-field">
            <label for="new_option_name_{{ $group->group_id }}">New option name</label>
            <input id="new_option_name_{{ $group->group_id }}" name="option_name" placeholder="e.g. Extra cheese" required data-testid="admin-option-create-name-{{ $group->group_id }}">
          </div>
          <div class="addon-field addon-field--narrow">
            <label for="new_price_delta_{{ $group->group_id }}">Extra price ($)</label>
            <input id="new_price_delta_{{ $group->group_id }}" name="price_delta" type="number" step="0.01" min="0" value="0" data-testid="admin-option-create-price-{{ $group->group_id }}">
          </div>
          <button class="btn btn-solid" type="submit" data-testid="admin-option-create-submit-{{ $group->group_id }}">Add option</button>
        </form>
      </div>
    </div>
  @endforeach

  <h3>Add a group</h3>
  <div class="addon-row addon-add">
    <form class="addon-form" method="POST" action="{{ route('admin.menu-items.groups.store', $item) }}">
      @csrf
      <div class="addon-field">
        <label for="new_group_name">Group name</label>
        <input id="new_group_name" name="group_name" placeholder="e.g. Sauce" required data-testid="admin-group-create-name">
      </div>
      <div class="addon-field addon-field--narrow">
        <label for="new_min_select">Min select</label>
        <input id="new_min_select" name="min_select" type="number" min="0" value="0" data-testid="admin-group-create-min">
      </div>
      <div class="addon-field addon-field--narrow">
        <label for="new_max_select">Max select</label>
        <input id="new_max_select" name="max_select" type="number" min="0" value="1" data-testid="admin-group-create-max">
      </div>
      <button class="btn btn-solid" type="submit" data-testid="admin-group-create-submit">Add group</button>
    </form>
  </div>
</div>
</x-layouts.admin>
