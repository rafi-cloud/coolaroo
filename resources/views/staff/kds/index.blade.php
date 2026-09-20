<x-layouts.staff
  :title="ucfirst($destination->value).' display'"
  :page-title="ucfirst($destination->value).' display'"
  :page-sub="$orders->count().' active order(s)'"
>
<div
  data-kds-page
  data-destination="{{ $destination->value }}"
  data-state-url="{{ route('staff.kds.state', ['destination' => $destination->value] + request()->query()) }}"
>
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>{{ session('status') === 'lines-started' ? 'Started.' : 'Marked ready.' }}</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form class="kds-filters" method="GET" action="{{ route('staff.kds.index', $destination->value) }}">
    <label for="kds-status">Line status</label>
    <select id="kds-status" name="status" data-testid="kds-filter-status">
      <option value="">All active</option>
      @foreach (['pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready'] as $value => $label)
        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
      @endforeach
    </select>

    <label for="kds-window">Placed within</label>
    <select id="kds-window" name="window" data-testid="kds-filter-window">
      <option value="">Any time</option>
      <option value="30" @selected($window === '30')>Last 30 min</option>
      <option value="60" @selected($window === '60')>Last hour</option>
    </select>

    <button type="submit" class="btn btn-ghost" data-testid="kds-filter-submit">Apply</button>

    @if (auth('staff')->user()->role->role_name === 'admin')
      <a
        class="btn btn-ghost"
        href="{{ route('staff.kds.index', $destination === \App\Enums\Destination::Kitchen ? 'bar' : 'kitchen') }}"
        data-testid="kds-switch-station"
      >Switch to {{ $destination === \App\Enums\Destination::Kitchen ? 'bar' : 'kitchen' }}</a>
    @endif
  </form>

  <div class="kds-grid">
    @forelse ($orders as $order)
      <article class="kds-card" data-testid="kds-order-{{ $order->order_id }}">
        <header class="kds-card-head">
          <div>
            <strong>#{{ $order->order_number }}</strong>
            <span class="muted">Table {{ $order->restaurantTable?->table_number ?? '—' }}</span>
          </div>
          <div class="kds-timing">
            <span class="kds-elapsed" data-testid="kds-elapsed-{{ $order->order_id }}">
              {{ $order->paid_at?->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) ?? '—' }}
            </span>
            @php($eta = $destination === \App\Enums\Destination::Kitchen ? $order->kitchen_eta_at : $order->bar_eta_at)
            @if ($eta)
              <span class="muted">ETA @auDateTime($eta)</span>
            @endif
          </div>
        </header>

        @if ($order->has_stock_conflict)
          <p class="kds-conflict" role="status" data-testid="kds-conflict-{{ $order->order_id }}">
            Stock conflict — resolve before making this order.
          </p>
        @endif

        <ul class="kds-lines">
          @foreach ($order->items as $line)
            <li>
              <span class="kds-qty">{{ $line->quantity }}&times;</span>
              <span class="kds-item">
                {{ $line->item_name }}
                <span class="muted">{{ $line->size_name }}</span>
              </span>
              <span class="badge b-{{ $line->status->value }}">{{ ucfirst($line->status->value) }}</span>

              @if (! empty($line->selected_options))
                <span class="kds-options">{{ collect($line->selected_options)->pluck('name')->filter()->join(', ') }}</span>
              @endif

              @if ($line->special_request)
                <span class="kds-request">&ldquo;{{ $line->special_request }}&rdquo;</span>
              @endif
            </li>
          @endforeach
        </ul>

        @php($lineStatuses = $order->items->pluck('status')->map->value)
        <footer class="kds-actions">
          @if ($lineStatuses->contains('pending'))
            <form method="POST" action="{{ route('staff.kds.start', [$order, $destination->value]) }}">
              @csrf
              <button type="submit" class="btn btn-solid" data-testid="kds-start-{{ $order->order_id }}">Start</button>
            </form>
          @endif

          @if ($lineStatuses->contains('preparing'))
            <form method="POST" action="{{ route('staff.kds.ready', [$order, $destination->value]) }}">
              @csrf
              <button type="submit" class="btn btn-solid" data-testid="kds-ready-{{ $order->order_id }}">Ready</button>
            </form>
          @endif
        </footer>
      </article>
    @empty
      <p data-testid="kds-empty">Nothing waiting at this station.</p>
    @endforelse
  </div>
</div>
</x-layouts.staff>
