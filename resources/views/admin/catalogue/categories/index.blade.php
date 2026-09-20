<x-layouts.admin title="Categories" page-title="Categories">
<div class="card">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @error('category')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  <a class="btn btn-solid" href="{{ route('admin.categories.create') }}" data-testid="admin-category-add">Add category</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Parent</th>
          <th>Order</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($categories as $category)
          <tr>
            <td>{{ $category->category_name }}</td>
            <td>{{ $category->parent?->category_name ?? '—' }}</td>
            <td>{{ $category->display_order }}</td>
            <td>
              @if ($category->is_active)
                <span class="badge b-active" data-testid="admin-category-status-{{ $category->category_id }}">Active</span>
              @else
                <span class="badge b-inactive" data-testid="admin-category-status-{{ $category->category_id }}">Deactivated</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.categories.edit', $category) }}" data-testid="admin-category-edit-{{ $category->category_id }}">Edit</a>

              @if ($category->is_active)
                <form method="POST" action="{{ route('admin.categories.deactivate', $category) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-ghost" data-testid="admin-category-deactivate-{{ $category->category_id }}">Deactivate</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.categories.reactivate', $category) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-category-reactivate-{{ $category->category_id }}">Reactivate</button>
                </form>
              @endif

              <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost" data-testid="admin-category-delete-{{ $category->category_id }}">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
