<x-layouts.admin title="Dietary tags" page-title="Dietary tags">
<div class="panel">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @error('tag')
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $message }}</span>
    </div>
  @enderror

  <a class="btn btn-solid" href="{{ route('admin.dietary-tags.create') }}" data-testid="admin-tag-add">Add dietary tag</a>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Description</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($tags as $tag)
          <tr>
            <td>{{ $tag->tag_name }}</td>
            <td>{{ $tag->description }}</td>
            <td>
              @if ($tag->is_active)
                <span class="badge b-active">Active</span>
              @else
                <span class="badge b-inactive">Inactive</span>
              @endif
            </td>
            <td>
              <a href="{{ route('admin.dietary-tags.edit', $tag) }}" data-testid="admin-tag-edit-{{ $tag->dietary_tag_id }}">Edit</a>
              <form method="POST" action="{{ route('admin.dietary-tags.destroy', $tag) }}" style="display:inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost" data-testid="admin-tag-delete-{{ $tag->dietary_tag_id }}">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
