<x-layouts.staff title="Floor view" page-title="Floor view" page-sub="{{ $tables->count() }} table(s)">
<div data-floor-page data-state-url="{{ route('staff.floor.state') }}">

  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>{{ match(session('status')) { 'table-seated' => 'Table seated.', 'table-cleared' => 'Table cleared.', 'order-served' => 'Marked served.', 'reservation-seated', 'reservation-assigned', 'reservation-released' => session('message', 'Booking updated.'), default => '' } }}</span>
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
    <h2>Needs attention</h2>
    <ul data-floor-alerts class="floor-alerts-list" data-testid="floor-alerts-list">
      @foreach ($attention as $tableId => $alert)
        <li class="floor-alert-item is-{{ $alert['kind'] }}" data-derived-alert data-testid="floor-alert-{{ $tableId }}">
          <strong>Table {{ $tables->firstWhere('table_id', $tableId)?->table_number ?? $tableId }}</strong>
          &mdash; {{ $alert['label'] }}
          <span class="muted">#{{ $alert['order_number'] }}</span>
        </li>
      @endforeach
      <li data-alerts-empty class="muted" data-testid="floor-alerts-empty" @if (count($attention)) hidden @endif>
        Nothing needs attention.
      </li>
    </ul>
  </section>

  <div class="kds-grid" data-testid="floor-grid">
    @foreach ($tables as $table)
      @php($alert = $attention[$table->table_id] ?? null)
      <article @class([
                 'kds-card',
                 'floor-table-card',
                 'needs-'.($alert['kind'] ?? '') => $alert,
                 'is-'.$table->status->value => ! $alert,
               ])
               data-table-row="{{ $table->table_id }}"
               data-table-card="{{ $table->table_id }}"
               data-rendered-status="{{ $table->status->value }}"
               data-testid="floor-table-{{ $table->table_id }}">
        <header class="kds-card-head">
          <div>
            <strong>Table {{ $table->table_number }}</strong>
            <span class="muted">{{ $table->section }} &middot; seats {{ $table->seat_capacity }}</span>
          </div>
          <span class="badge b-{{ $table->status->value }}" data-table-status="{{ $table->table_id }}" data-testid="floor-table-status-{{ $table->table_id }}">
            {{ ucfirst($table->status->value) }}
          </span>
        </header>

        <p class="floor-table-attention" data-table-attention="{{ $table->table_id }}"
           data-testid="floor-table-attention-{{ $table->table_id }}" @unless ($alert) hidden @endunless>
          {{ $alert['label'] ?? '' }}
        </p>

        <p data-table-orders="{{ $table->table_id }}" data-testid="floor-table-orders-{{ $table->table_id }}">
          {{ $table->active_order_count }} active order(s)
        </p>

        <div class="floor-table-links">
          <a href="{{ route('staff.tables.order', $table) }}" class="btn btn-ghost" data-testid="floor-table-order-link-{{ $table->table_id }}">
            Take order
          </a>
          <a href="{{ route('staff.tables.refunds', $table) }}" class="btn btn-ghost" data-testid="floor-table-refund-link-{{ $table->table_id }}">
            Request a refund
          </a>
        </div>

        @php($nextVisit = $table->visits->first())
        @php($nextReservation = $nextVisit?->reservation)
        <p data-table-reservation="{{ $table->table_id }}" data-testid="floor-table-reservation-{{ $table->table_id }}">
          @if ($nextReservation && $nextVisit->opened_at !== null)
            Seated: {{ $nextReservation->reference_code }}, {{ $nextReservation->party_size }} guests
          @elseif ($nextReservation)
            Next: {{ $nextReservation->reference_code }}, {{ $nextReservation->party_size }} guests, {{ $nextReservation->slot->slot_time }}
          @else
            No upcoming reservation
          @endif
        </p>

        <x-floor.table-drawer :table="$table" :tables="$tables" :assignable="$assignableReservations" />
      </article>
    @endforeach
  </div>

  <section class="floor-list-panel">
    <h2>Ready to serve</h2>
    <ul data-ready-to-serve-list data-testid="floor-ready-list">
      @forelse ($readyToServe as $order)
        <li data-order-row="{{ $order->order_id }}">
          #{{ $order->order_number }} &mdash; table {{ $order->restaurantTable?->table_number }}
          @foreach ($order->items->pluck('destination')->unique() as $destination)
            <form method="POST" action="{{ route('staff.orders.serve', [$order, $destination->value]) }}" style="display:inline">
              @csrf
              <button type="submit" class="btn btn-ghost" data-testid="floor-serve-{{ $order->order_id }}-{{ $destination->value }}">Serve {{ ucfirst($destination->value) }}</button>
            </form>
          @endforeach
        </li>
      @empty
        <li data-testid="floor-ready-empty">Nothing ready.</li>
      @endforelse
    </ul>
  </section>

  <section class="floor-list-panel">
    <h2>Cash waiting</h2>
    <ul data-cash-waiting-list data-testid="floor-cash-list">
      @forelse ($cashWaiting as $payment)
        <li data-payment-row="{{ $payment->payment_id }}" class="floor-cash-row">
          <span class="floor-cash-detail">
            #{{ $payment->order->order_number }} &mdash; table {{ $payment->order->restaurantTable?->table_number ?? '—' }} &mdash; @money($payment->amount)
          </span>
          @if ($payment->order->table_id)
            <a class="btn btn-solid"
               href="{{ route('staff.tables.order', $payment->order->table_id) }}"
               data-testid="floor-cash-settle-{{ $payment->payment_id }}">Take payment</a>
          @endif
        </li>
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

  // Each drawer offers Seat or Clear based on the status the page was rendered
  // with, and lists the tables that were free or occupied at that moment. None
  // of that can be patched from the state payload without rebuilding the form,
  // so a table status change re-renders the page — deferred while a drawer is
  // open so a waiter is never reloaded mid-action.
  let pendingReload = false;

  const reloadWhenIdle = () => {
    if (page.querySelector('details[open]')) {
      pendingReload = true;

      return;
    }

    window.location.reload();
  };

  page.addEventListener('toggle', () => {
    if (pendingReload && ! page.querySelector('details[open]')) {
      window.location.reload();
    }
  }, true);

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

      const card = page.querySelector(`[data-table-card="${table.table_id}"]`);
      if (card) {
        card.className = 'kds-card floor-table-card '
          + (table.attention ? 'needs-' + table.attention : 'is-' + table.status);
      }

      const attention = page.querySelector(`[data-table-attention="${table.table_id}"]`);
      if (attention) {
        attention.textContent = table.attention_label ?? '';
        attention.hidden = ! table.attention;
      }

      const orders = page.querySelector(`[data-table-orders="${table.table_id}"]`);
      if (orders) {
        orders.textContent = table.active_order_count + ' active order(s)';
      }

      const reservation = page.querySelector(`[data-table-reservation="${table.table_id}"]`);
      if (reservation) {
        const booking = table.next_reservation;

        if (! booking) {
          reservation.textContent = 'No upcoming reservation';
        } else if (booking.seated) {
          reservation.textContent = `Seated: ${booking.reference_code}, ${booking.party_size} guests`;
        } else {
          reservation.textContent = `Next: ${booking.reference_code}, ${booking.party_size} guests, ${booking.slot_time}`;
        }
      }
    });

    // Rebuild only the derived rows; the ephemeral "waiter called" alerts
    // floor.js prepends are not in the payload and must survive a refresh.
    const alertList = page.querySelector('[data-floor-alerts]');
    if (alertList) {
      alertList.querySelectorAll('[data-derived-alert]').forEach((row) => row.remove());

      const emptyRow = alertList.querySelector('[data-alerts-empty]');
      const tableNumber = {};
      state.tables.forEach((t) => { tableNumber[t.table_id] = t.table_number; });

      (state.attention ?? []).forEach((alert) => {
        const row = document.createElement('li');
        row.className = 'floor-alert-item is-' + alert.kind;
        row.setAttribute('data-derived-alert', '');
        row.setAttribute('data-testid', 'floor-alert-' + alert.table_id);
        row.innerHTML = `<strong>Table ${tableNumber[alert.table_id] ?? alert.table_id}</strong> &mdash; ${alert.label} <span class="muted">#${alert.order_number}</span>`;
        alertList.insertBefore(row, emptyRow);
      });

      if (emptyRow) {
        emptyRow.hidden = alertList.querySelectorAll('li:not([data-alerts-empty])').length > 0;
      }
    }

    const readyList = page.querySelector('[data-ready-to-serve-list]');
    if (readyList) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
      readyList.innerHTML = state.ready_to_serve.length
        ? state.ready_to_serve.map((order) => {
            const buttons = order.destinations.map((destination) => `
              <form method="POST" action="/staff/orders/${order.order_id}/serve/${destination}" style="display:inline">
                <input type="hidden" name="_token" value="${csrfToken}">
                <button type="submit" class="btn btn-ghost">Serve ${destination}</button>
              </form>
            `).join('');
            return `<li data-order-row="${order.order_id}">#${order.order_number} &mdash; table ${order.table_number ?? '&mdash;'} ${buttons}</li>`;
          }).join('')
        : '<li>Nothing ready.</li>';
    }

    const cashList = page.querySelector('[data-cash-waiting-list]');
    if (cashList) {
      cashList.innerHTML = state.cash_waiting.length
        ? state.cash_waiting.map((payment) => {
            const settle = payment.settle_url
              ? `<a class="btn btn-solid" href="${payment.settle_url}" data-testid="floor-cash-settle-${payment.payment_id}">Take payment</a>`
              : '';

            return `<li data-payment-row="${payment.payment_id}" class="floor-cash-row">`
              + `<span class="floor-cash-detail">#${payment.order_number} &mdash; table ${payment.table_number ?? '&mdash;'} &mdash; $${payment.amount.toFixed(2)}</span>`
              + settle
              + '</li>';
          }).join('')
        : '<li>Nothing waiting.</li>';
    }

    const statusChanged = state.tables.some((table) => {
      const card = page.querySelector(`[data-table-card="${table.table_id}"]`);

      return card && card.dataset.renderedStatus !== table.status;
    });

    if (statusChanged) {
      reloadWhenIdle();
    }
  });
});
</script>
@endpush
</x-layouts.staff>
