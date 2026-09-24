<x-layouts.admin title="Customer — {{ $customer->full_name }}" page-title="Customer profile">
  <div class="card" data-testid="admin-customer-detail-page">
    <div style="margin-bottom:1rem">
      <a href="{{ route('admin.customers.index') }}" class="btn btn-xs btn-outline" data-testid="admin-customers-back-btn">&larr; Back to customers</a>
    </div>

    @if (session('status'))
      <div class="alert alert-success" role="status" style="margin-bottom:1rem">
        {{ session('message') ?? session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger" role="alert" style="margin-bottom:1rem">
        <ul style="margin:0; padding-left:1.2rem">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="customer-overview" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem; margin-bottom:1.5rem; padding-bottom:1.2rem; border-bottom:1px solid var(--line);">
      <div>
        <h2 style="margin:0 0 0.4rem 0" data-testid="admin-customer-name">{{ $customer->full_name }}</h2>
        <div class="muted" style="display:flex; flex-direction:column; gap:0.2rem; font-size:0.88rem;">
          <span><strong>Email:</strong> {{ $customer->email }}</span>
          @if ($customer->phone)
            <span><strong>Phone:</strong> {{ $customer->phone }}</span>
          @endif
          <span><strong>Member since:</strong> @auDate($customer->created_at)</span>
        </div>
      </div>

      <!-- Trust Profile Box -->
      @php
        $badgeClass = match ($profile['badge']) {
            'Flagged' => 'badge-flagged',
            'Regular' => 'badge-regular',
            default => 'badge-new',
        };
      @endphp
      <div class="trust-profile-card" style="min-width:280px; max-width:340px;" data-testid="admin-customer-trust-card">
        <div class="trust-header">
          <h4>Trust profile</h4>
          <span class="trust-badge {{ $badgeClass }}" data-testid="admin-customer-trust-badge">
            {{ $profile['badge'] }}
          </span>
        </div>

        <div class="trust-stats-grid">
          <div class="stat-box">
            <span class="stat-num" data-testid="admin-stat-completed">{{ $profile['completed_visits_count'] }}</span>
            <span class="stat-lbl">Visits</span>
          </div>
          <div class="stat-box">
            <span class="stat-num" data-testid="admin-stat-cancelled">{{ $profile['cancellations_count'] }}</span>
            <span class="stat-lbl">Cancelled</span>
          </div>
          <div class="stat-box {{ $profile['uncleared_no_shows_count'] > 0 ? 'stat-box-danger' : '' }}">
            <span class="stat-num" data-testid="admin-stat-no-shows">{{ $profile['uncleared_no_shows_count'] }}</span>
            <span class="stat-lbl">No-shows</span>
          </div>
        </div>

        @if ($profile['badge'] === 'Flagged')
          <p class="trust-warning" data-testid="admin-customer-flagged-warning">
            Account flagged due to uncleared no-show within the last 12 months.
          </p>
        @endif
      </div>
    </div>

    <!-- Reservation history -->
    <h3 style="margin-bottom:0.8rem">Reservation history</h3>
    <div class="table-scroll">
      <table class="table" data-testid="admin-customer-reservations-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Reference</th>
            <th>Party</th>
            <th>Status</th>
            <th>Table(s)</th>
            <th>No-show details / Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($reservations as $r)
            @php
              $statusClass = match ($r->status) {
                  \App\Enums\ReservationStatus::Requested => 'b-pending',
                  \App\Enums\ReservationStatus::Confirmed => 'b-preparing',
                  \App\Enums\ReservationStatus::Seated => 'b-ready',
                  \App\Enums\ReservationStatus::Completed => 'b-served',
                  default => 'b-cancelled',
              };
              $activeTables = $r->visits->whereNull('closed_at')->map(fn($v) => $v->restaurantTable)->filter();
            @endphp
            <tr data-testid="admin-reservation-row-{{ $r->reservation_id }}">
              <td>
                <div><strong>@auDate($r->booking_date)</strong></div>
                <div class="muted text-xs">{{ substr($r->booking_time, 0, 5) }}</div>
              </td>
              <td>
                <span style="font-family:monospace">{{ $r->reference_code }}</span>
              </td>
              <td>{{ $r->party_size }} {{ \Illuminate\Support\Str::plural('guest', $r->party_size) }}</td>
              <td>
                <span class="badge {{ $statusClass }}">
                  {{ ucfirst($r->status->value) }}
                </span>
              </td>
              <td>
                @if ($activeTables->isNotEmpty())
                  {{ $activeTables->pluck('table_number')->map(fn($n) => 'T'.$n)->join(', ') }}
                @else
                  <span class="muted">—</span>
                @endif
              </td>
              <td>
                @if ($r->status === \App\Enums\ReservationStatus::NoShow)
                  @if ($r->no_show_cleared_at)
                    <div class="text-xs" data-testid="admin-no-show-cleared-info-{{ $r->reservation_id }}">
                      <span class="text-success">✓ Cleared</span> by {{ $r->noShowClearedBy?->full_name ?? 'Admin' }}
                      <div><em class="muted">&ldquo;{{ $r->no_show_clear_reason }}&rdquo;</em></div>
                    </div>
                  @else
                    <details class="clear-no-show-drawer" data-testid="admin-clear-no-show-drawer-{{ $r->reservation_id }}">
                      <summary class="btn btn-xs btn-outline" data-testid="admin-clear-no-show-toggle-{{ $r->reservation_id }}">
                        Clear no-show flag
                      </summary>
                      <form method="POST" action="{{ route('admin.customers.no-shows.clear', ['customer' => $customer, 'reservation' => $r]) }}" style="margin-top:0.5rem; padding:0.6rem; background:var(--cream); border:1px solid var(--line); border-radius:6px;" data-testid="admin-clear-no-show-form-{{ $r->reservation_id }}">
                        @csrf
                        <div class="form-group" style="margin-bottom:0.4rem">
                          <label for="clear-reason-{{ $r->reservation_id }}" class="text-xs" style="display:block; font-weight:600; margin-bottom:0.2rem">
                            Justification reason (required, audited)
                          </label>
                          <input type="text" id="clear-reason-{{ $r->reservation_id }}" name="reason" required maxlength="255" placeholder="e.g. Guest phoned to apologize; family emergency" style="width:100%; font-size:0.82rem; padding:0.3rem 0.5rem; border:1px solid var(--line); border-radius:4px;" data-testid="admin-clear-no-show-reason-{{ $r->reservation_id }}">
                        </div>
                        <button type="submit" class="btn btn-xs btn-primary" data-testid="admin-clear-no-show-submit-{{ $r->reservation_id }}">
                          Confirm clear flag
                        </button>
                      </form>
                    </details>
                  @endif
                @else
                  <span class="muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center muted">No reservations on record for this customer.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <h3 style="margin:1.8rem 0 0.8rem">Order history</h3>

    <div class="customer-order-summary" data-testid="admin-customer-order-summary">
      <div class="customer-order-stat">
        <span class="customer-order-stat-value">{{ $orderTotals['count'] }}</span>
        <span class="customer-order-stat-label">Orders placed</span>
      </div>
      <div class="customer-order-stat">
        <span class="customer-order-stat-value">@money($orderTotals['spend'])</span>
        <span class="customer-order-stat-label">Lifetime spend</span>
      </div>
      <div class="customer-order-stat">
        <span class="customer-order-stat-value">{{ $orderTotals['last'] ? $orderTotals['last']->diffForHumans() : '—' }}</span>
        <span class="customer-order-stat-label">Last order</span>
      </div>
    </div>

    <div class="table-scroll">
      <table class="table" data-testid="admin-customer-orders-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Placed</th>
            <th>Table</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($orders as $order)
            <tr data-testid="admin-customer-order-row-{{ $order->order_id }}">
              <td>{{ $order->order_number }}</td>
              <td>@auDateTime($order->placed_at)</td>
              <td>{{ $order->restaurantTable?->table_number ? 'T'.$order->restaurantTable->table_number : '—' }}</td>
              <td>{{ $order->items_count }}</td>
              <td>@money($order->total_amount)</td>
              <td><span class="badge b-{{ $order->status->value }}">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</span></td>
              <td><a href="{{ route('admin.orders.show', $order) }}" data-testid="admin-customer-order-view-{{ $order->order_id }}">View</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center muted">No orders on record for this customer.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</x-layouts.admin>
