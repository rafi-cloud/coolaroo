<x-layouts.admin title="Menu items" page-title="Menu items">
<div class="panel">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @error('featured')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  <a class="btn btn-solid" href="{{ route('admin.menu-items.create') }}" data-testid="admin-menu-item-add">Add menu item</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Category</th>
          <th>Station</th>
          <th>Featured</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $item)
          <tr>
            <td class="cell-item">
              @if ($item->image_path)
                <img src="{{ asset('storage/'.$item->image_path) }}" alt="">
              @endif
              <span><strong>{{ $item->item_name }}</strong></span>
            </td>
            <td>{{ $item->category->category_name }}</td>
            <td>{{ ucfirst($item->destination->value) }}</td>
            <td>
              <form method="POST" action="{{ route('admin.menu-items.toggle-featured', $item) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-ghost" data-testid="admin-menu-item-featured-{{ $item->item_id }}">{{ $item->is_featured ? 'Featured' : 'Not featured' }}</button>
              </form>
            </td>
            <td>
              @if ($item->is_active)
                <span class="badge b-active" data-testid="admin-menu-item-status-{{ $item->item_id }}">Active</span>
              @else
                <span class="badge b-inactive" data-testid="admin-menu-item-status-{{ $item->item_id }}">Archived</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.menu-items.edit', $item) }}" data-testid="admin-menu-item-edit-{{ $item->item_id }}">Edit</a>

              @if ($item->is_active)
                <form method="POST" action="{{ route('admin.menu-items.archive', $item) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-ghost" data-testid="admin-menu-item-archive-{{ $item->item_id }}">Archive</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.menu-items.unarchive', $item) }}" style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-menu-item-unarchive-{{ $item->item_id }}">Unarchive</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
