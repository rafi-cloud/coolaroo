<x-layouts.staff title="Floor view" page-title="Floor view" page-sub="{{ $tables->count() }} table(s)">
<div data-floor-page data-state-url="{{ route('staff.floor.state') }}">

  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>{{ match(session('status')) { 'table-seated' => 'Table seated.', 'table-cleared' => 'Table cleared.', default => '' } }}</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <div data-paused-banner class="floor-paused-banner" role="status" @if (! $qrOrderingPaused) hidden @endif data-testid="floor-paused-banner">
    QR ordering is paused &mdash; customers can browse but not order.
  </div>

  <section class="floor-alerts-panel">
    <h2>Alerts</h2>
    <ul data-floor-alerts class="floor-alerts-list" data-testid="floor-alerts-list"></ul>
  </section>

  <div class="kds-grid" data-testid="floor-grid">
    @foreach ($tables as $table)
      <article class="kds-card" data-table-row="{{ $table->table_id }}" data-testid="floor-table-{{ $table->table_id }}">
        <header class="kds-card-head">
          <div>
            <strong>Table {{ $table->table_number }}</strong>
            <span class="muted">{{ $table->section }} &middot; seats {{ $table->seat_capacity }}</span>
          </div>
          <span class="badge b-{{ $table->status->value }}" data-table-status="{{ $table->table_id }}" data-testid="floor-table-status-{{ $table->table_id }}">
            {{ ucfirst($table->status->value) }}
          </span>
        </header>

        <p data-table-orders="{{ $table->table_id }}" data-testid="floor-table-orders-{{ $table->table_id }}">
          {{ $table->active_order_count }} active order(s)
        </p>

        @php($nextReservation = $table->visits->first()?->reservation)
        <p data-table-reservation="{{ $table->table_id }}" data-testid="floor-table-reservation-{{ $table->table_id }}">
          @if ($nextReservation)
            Next: {{ $nextReservation->party_size }} guests, {{ $nextReservation->slot->slot_time }}
          @else
            No upcoming reservation
          @endif
        </p>

        <x-floor.table-drawer :table="$table" :tables="$tables" />
      </article>
    @endforeach
  </div>

  <section class="floor-list-panel">
    <h2>Ready to serve</h2>
    <ul data-ready-to-serve-list data-testid="floor-ready-list">
      @forelse ($readyToServe as $order)
        <li data-order-row="{{ $order->order_id }}">#{{ $order->order_number }} &mdash; table {{ $order->restaurantTable?->table_number }}</li>
      @empty
        <li data-testid="floor-ready-empty">Nothing ready.</li>
      @endforelse
    </ul>
  </section>

  <section class="floor-list-panel">
    <h2>Cash waiting</h2>
    <ul data-cash-waiting-list data-testid="floor-cash-list">
      @forelse ($cashWaiting as $payment)
        <li data-payment-row="{{ $payment->payment_id }}">#{{ $payment->order->order_number }} &mdash; table {{ $payment->order->restaurantTable?->table_number }} &mdash; @money($payment->amount)</li>
      @empty
        <li data-testid="floor-cash-empty">Nothing waiting.</li>
      @endforelse
    </ul>
  </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const page = document.querySelector('[data-floor-page]');
  if (!page) {
    return;
  }

  page.addEventListener('floor:state', (event) => {
    const state = event.detail;

    const banner = document.querySelector('[data-paused-banner]');
    if (banner) {
      banner.hidden = ! state.qr_ordering_paused;
    }

    state.tables.forEach((table) => {
      const badge = page.querySelector(`[data-table-status="${table.table_id}"]`);
      if (badge) {
        badge.textContent = table.status.charAt(0).toUpperCase() + table.status.slice(1);
        badge.className = 'badge b-' + table.status;
      }

      const orders = page.querySelector(`[data-table-orders="${table.table_id}"]`);
      if (orders) {
        orders.textContent = table.active_order_count + ' active order(s)';
      }

      const reservation = page.querySelector(`[data-table-reservation="${table.table_id}"]`);
      if (reservation) {
        reservation.textContent = table.next_reservation
          ? `Next: ${table.next_reservation.party_size} guests, ${table.next_reservation.slot_time}`
          : 'No upcoming reservation';
      }
    });

    const readyList = page.querySelector('[data-ready-to-serve-list]');
    if (readyList) {
      readyList.innerHTML = state.ready_to_serve.length
        ? state.ready_to_serve.map((order) => `<li data-order-row="${order.order_id}">#${order.order_number} &mdash; table ${order.table_number ?? '&mdash;'}</li>`).join('')
        : '<li>Nothing ready.</li>';
    }

    const cashList = page.querySelector('[data-cash-waiting-list]');
    if (cashList) {
      cashList.innerHTML = state.cash_waiting.length
        ? state.cash_waiting.map((payment) => `<li data-payment-row="${payment.payment_id}">#${payment.order_number} &mdash; table ${payment.table_number ?? '&mdash;'}</li>`).join('')
        : '<li>Nothing waiting.</li>';
    }
  });
});
</script>
@endpush
</x-layouts.staff>
