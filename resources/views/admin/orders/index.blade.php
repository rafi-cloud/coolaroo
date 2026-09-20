<x-layouts.admin title="Orders" page-title="Orders">
<div class="card">
  <form method="GET" action="{{ route('admin.orders.index') }}" class="kds-filters">
    <label for="order-number">Number</label>
    <input id="order-number" name="number" value="{{ $filters['number'] ?? '' }}" data-testid="admin-orders-filter-number">

    <label for="order-date">Date</label>
    <input type="date" id="order-date" name="date" value="{{ $filters['date'] ?? '' }}" data-testid="admin-orders-filter-date">

    <label for="order-table">Table</label>
    <select id="order-table" name="table_id" data-testid="admin-orders-filter-table">
      <option value="">All</option>
      @foreach ($tables as $table)
        <option value="{{ $table->table_id }}" @selected(($filters['table_id'] ?? null) == $table->table_id)>{{ $table->table_number }}</option>
      @endforeach
    </select>

    <label for="order-status">Status</label>
    <select id="order-status" name="status" data-testid="admin-orders-filter-status">
      <option value="">All</option>
      @foreach ($statuses as $case)
        <option value="{{ $case->value }}" @selected(($filters['status'] ?? null) === $case->value)>{{ ucfirst(str_replace('_', ' ', $case->value)) }}</option>
      @endforeach
    </select>

    <button type="submit" class="btn btn-ghost" data-testid="admin-orders-filter-submit">Search</button>
  </form>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Number</th><th>Table</th><th>Status</th><th>Total</th><th>Placed</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($orders as $order)
          <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->restaurantTable?->table_number ?? '—' }}</td>
            <td><span class="badge b-{{ $order->status->value }}">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</span></td>
            <td>@money($order->total_amount)</td>
            <td>@auDateTime($order->placed_at)</td>
            <td><a href="{{ route('admin.orders.show', $order) }}" data-testid="admin-orders-view-{{ $order->order_id }}">View</a></td>
          </tr>
        @empty
          <tr><td colspan="6">No orders match this search.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $orders->links() }}
</div>
</x-layouts.admin>
