<x-layouts.admin title="Customers" page-title="Customers">
  <div class="card" data-testid="admin-customers-page">
    @if (session('status'))
      <div class="alert alert-success" role="status" style="margin-bottom:1rem">
        {{ session('message') ?? session('status') }}
      </div>
    @endif

    <form method="GET" action="{{ route('admin.customers.index') }}" class="kds-filters" data-testid="admin-customers-search-form">
      <label for="customer-search">Search</label>
      <input type="text" id="customer-search" name="search" value="{{ $search }}" placeholder="Name, email, or phone" data-testid="admin-customers-search-input">

      <button type="submit" class="btn btn-ghost" data-testid="admin-customers-search-submit">Search</button>
      @if ($search !== '')
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline" data-testid="admin-customers-search-clear">Clear</a>
      @endif
    </form>

    <div class="table-scroll" style="margin-top:1.2rem">
      <table class="table" data-testid="admin-customers-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Trust badge</th>
            <th>Bookings</th>
            <th>Completed</th>
            <th>No-shows</th>
            <th>Member since</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($customers as $c)
            @php
              $badgeClass = match ($c->trust_badge) {
                  'Flagged' => 'badge-flagged',
                  'Regular' => 'badge-regular',
                  default => 'badge-new',
              };
            @endphp
            <tr data-testid="admin-customer-row-{{ $c->customer_id }}">
              <td>
                <strong>{{ $c->full_name }}</strong>
              </td>
              <td>
                <div>{{ $c->email }}</div>
                @if ($c->phone)
                  <div class="muted text-xs">{{ $c->phone }}</div>
                @endif
              </td>
              <td>
                <span class="trust-badge {{ $badgeClass }}" data-testid="admin-customer-badge-{{ $c->customer_id }}">
                  {{ $c->trust_badge }}
                </span>
              </td>
              <td>{{ $c->reservations_count }}</td>
              <td>{{ $c->completed_reservations_count }}</td>
              <td>
                @if ($c->uncleared_no_shows_count > 0)
                  <span class="text-danger" style="font-weight:700" data-testid="admin-customer-no-shows-{{ $c->customer_id }}">
                    {{ $c->uncleared_no_shows_count }}
                  </span>
                @else
                  <span class="muted">0</span>
                @endif
              </td>
              <td>@auDate($c->created_at)</td>
              <td>
                <a href="{{ route('admin.customers.show', $c) }}" class="btn btn-xs btn-outline" data-testid="admin-customer-view-{{ $c->customer_id }}">
                  View profile
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center muted">
                No customers found{{ $search !== '' ? " matching '{$search}'" : '' }}.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:1rem">
      {{ $customers->links() }}
    </div>
  </div>
</x-layouts.admin>
