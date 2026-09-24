<x-layouts.admin title="Dashboard" page-title="Admin Dashboard" page-sub="Live Venue Performance · 15 Widgets">
  <div class="block" id="dashboard">
    <div class="block-head">
      <div>
        <p class="eyebrow">Overview</p>
        <h2>Live Operations</h2>
      </div>
      <div class="toolbar-right">
        <a class="btn btn-quiet" href="{{ route('admin.dashboard') }}" data-testid="admin-refresh-btn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-right:4px"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
          Refresh
        </a>
      </div>
    </div>

        <div class="dashboard-grid">
            <article class="card tile" data-testid="admin-tile-sales">
        <div class="tile-top">
          <span class="tile-ico ico-orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </span>
          <span class="tag tag-gold">AUD Gross</span>
        </div>
        <p class="tile-value">${{ number_format($dashboard['sales_today']['amount'], 2) }}</p>
        <p class="tile-name">Sales today</p>
        @if ($dashboard['sales_today']['change_pct'] !== null)
          <p class="tile-change {{ $dashboard['sales_today']['change_pct'] >= 0 ? 'up' : 'down' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="{{ $dashboard['sales_today']['change_pct'] >= 0 ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}"></path>
            </svg>
            {{ abs($dashboard['sales_today']['change_pct']) }}% <span>vs same day last week (${{ number_format($dashboard['sales_today']['previous'], 2) }})</span>
          </p>
        @else
          <p class="tile-change">
            <span>vs last week: ${{ number_format($dashboard['sales_today']['previous'], 2) }}</span>
          </p>
        @endif
      </article>

            <article class="card tile" data-testid="admin-tile-orders">
        <div class="tile-top">
          <span class="tile-ico ico-green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4h12l1 16H5z"></path><path d="M9 9h6"></path></svg>
          </span>
          <span class="tag tag-new">Paid</span>
        </div>
        <p class="tile-value">{{ $dashboard['orders_today'] }}</p>
        <p class="tile-name">Orders today</p>
        <p class="tile-change">
          <span>Completed & paid orders</span>
        </p>
      </article>

            <article class="card tile" data-testid="admin-tile-aov">
        <div class="tile-top">
          <span class="tile-ico ico-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 6v6l4 2"></path></svg>
          </span>
          <span class="tag tag-quiet">AOV</span>
        </div>
        <p class="tile-value">${{ number_format($dashboard['average_order_value'], 2) }}</p>
        <p class="tile-name">Average order value</p>
        <p class="tile-change">
          <span>Per paid transaction</span>
        </p>
      </article>

            <article class="card tile" data-testid="admin-tile-payment-split">
        <div class="tile-top">
          <span class="tile-ico ico-orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg>
          </span>
          <span class="tag tag-quiet">Split</span>
        </div>
        <p class="tile-value">${{ number_format($dashboard['cash_vs_stripe']['cash'] + $dashboard['cash_vs_stripe']['stripe'], 2) }}</p>
        <p class="tile-name">Cash: ${{ number_format($dashboard['cash_vs_stripe']['cash'], 2) }} · Stripe: ${{ number_format($dashboard['cash_vs_stripe']['stripe'], 2) }}</p>
        <div class="split-meter" title="Cash vs Stripe share">
          <div class="split-bar-cash" style="width: {{ $dashboard['cash_vs_stripe']['cash_pct'] ?? 50 }}%"></div>
          <div class="split-bar-stripe" style="width: {{ 100 - ($dashboard['cash_vs_stripe']['cash_pct'] ?? 50) }}%"></div>
        </div>
        <p class="tile-change">
          <span>Cash share: {{ $dashboard['cash_vs_stripe']['cash_pct'] !== null ? $dashboard['cash_vs_stripe']['cash_pct'].'%' : '0%' }}</span>
        </p>
      </article>

            <article class="card tile" data-testid="admin-tile-covers">
        <div class="tile-top">
          <span class="tile-ico ico-green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="4"></circle><path d="M17 11a3 3 0 1 0-2.8-4"></path><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"></path><path d="M16 15a4 4 0 0 1 4 4v2"></path></svg>
          </span>
          <span class="tag tag-new">Floor</span>
        </div>
        <p class="tile-value">{{ $dashboard['covers_booked_today'] }}</p>
        <p class="tile-name">Covers booked today</p>
        <p class="tile-change">
          <span>Confirmed & seated party covers</span>
        </p>
      </article>

            <article class="card tile" data-testid="admin-tile-reservations">
        <div class="tile-top">
          <span class="tile-ico ico-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
          </span>
          <span class="tag tag-gold">{{ $dashboard['pending_reservation_requests'] > 0 ? 'Action' : 'Quiet' }}</span>
        </div>
        <p class="tile-value">{{ $dashboard['pending_reservation_requests'] }}</p>
        <p class="tile-name">Pending reservations</p>
        <a class="tile-link" href="{{ route('staff.reservations.index', ['status' => 'requested']) }}" data-testid="admin-link-reservations">
          Review on board &rarr;
        </a>
      </article>

            <article class="card tile" data-testid="admin-tile-refunds">
        <div class="tile-top">
          <span class="tile-ico {{ $dashboard['open_refund_requests'] > 0 ? 'ico-orange' : 'ico-green' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14L4 9l5-5"></path><path d="M4 9h11a5 5 0 0 1 0 10h-3"></path></svg>
          </span>
          <span class="tag {{ $dashboard['open_refund_requests'] > 0 ? 'tag-gold' : 'tag-quiet' }}">{{ $dashboard['open_refund_requests'] }} Open</span>
        </div>
        <p class="tile-value">{{ $dashboard['open_refund_requests'] }}</p>
        <p class="tile-name">Open refund requests</p>
        <a class="tile-link" href="{{ route('admin.refunds.index') }}" data-testid="admin-link-refunds">
          View refund queue &rarr;
        </a>
      </article>

            <article class="card tile" data-testid="admin-tile-rating">
        <div class="tile-top">
          <span class="tile-ico ico-amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          </span>
          <span class="tag tag-quiet">{{ $dashboard['average_rating']['count'] }} reviews</span>
        </div>
        <p class="tile-value">
          {{ $dashboard['average_rating']['food'] !== null ? number_format(($dashboard['average_rating']['food'] + $dashboard['average_rating']['service']) / 2, 1) : '—' }}<span style="font-size:1rem;color:var(--cancelled)">/5</span>
        </p>
        <p class="tile-name">Food {{ $dashboard['average_rating']['food'] ?? '—' }} · Service {{ $dashboard['average_rating']['service'] ?? '—' }}</p>
        <p class="tile-change">
          <span>Last 30 days (excluding hidden)</span>
        </p>
      </article>
    </div>

        <div class="dashboard-grid">
            <article class="card" style="grid-column: span 6" data-testid="admin-chart-hourly">
        <header class="card-head">
          <div>
            <h3>Sales by hour today</h3>
            <p class="card-sub">Gross takings per hour (00:00 – 23:00)</p>
          </div>
        </header>

        @php
          $maxHourly = max(array_merge([1.0], array_column($dashboard['sales_by_hour'], 'amount')));
        @endphp
        <div class="chart-hourly-bars" role="img" aria-label="Bar chart showing hourly sales today">
          @foreach ($dashboard['sales_by_hour'] as $slot)
            @php
              $pct = ($slot['amount'] / $maxHourly) * 100;
              $hourLabel = sprintf('%02d', $slot['hour']);
            @endphp
            <div class="hourly-bar-col" title="{{ $hourLabel }}:00 — ${{ number_format($slot['amount'], 2) }}">
              <div class="hourly-bar" style="height: {{ max(4, $pct) }}%"></div>
              @if ($slot['hour'] % 4 === 0)
                <span class="hourly-bar-label">{{ $slot['hour'] }}h</span>
              @endif
            </div>
          @endforeach
        </div>
        <ul class="chart-axis">
          <li>12am</li><li>4am</li><li>8am</li><li>12pm</li><li>4pm</li><li>8pm</li><li>11pm</li>
        </ul>
      </article>

            <article class="card" style="grid-column: span 6" data-testid="admin-chart-trend">
        <header class="card-head">
          <div>
            <h3>Sales trend</h3>
            <p class="card-sub">Daily paid revenue history</p>
          </div>
          <div class="tabs" role="group" aria-label="Trend window">
            <button type="button" class="{{ $currentTrendDays === 7 ? 'is-on' : '' }}" data-trend-toggle="7" data-testid="admin-trend-7d" aria-pressed="{{ $currentTrendDays === 7 ? 'true' : 'false' }}">7 Days</button>
            <button type="button" class="{{ $currentTrendDays === 30 ? 'is-on' : '' }}" data-trend-toggle="30" data-testid="admin-trend-30d" aria-pressed="{{ $currentTrendDays === 30 ? 'true' : 'false' }}">30 Days</button>
          </div>
        </header>

                @php
          $max7 = max(array_merge([1.0], array_column($trend7, 'amount')));
          $n7 = count($trend7);
          $points7 = [];
          $svgW = 560;
          $svgH = 150;
          $padX = 20;
          $padY = 20;
          $drawW = $svgW - ($padX * 2);
          $drawH = $svgH - ($padY * 2);

          foreach ($trend7 as $idx => $t) {
              $x = $padX + ($n7 > 1 ? ($idx / ($n7 - 1)) * $drawW : $drawW / 2);
              $y = $padY + $drawH - (($t['amount'] / $max7) * $drawH);
              $points7[] = sprintf('%.1f,%.1f', $x, $y);
          }
          $polyStr7 = implode(' ', $points7);
          $areaStr7 = "M {$padX}," . ($padY + $drawH) . " L " . implode(' L ', $points7) . " L " . ($padX + $drawW) . "," . ($padY + $drawH) . " Z";
        @endphp
        <div data-trend-target="7" style="{{ $currentTrendDays === 7 ? '' : 'display:none' }}">
          <svg class="chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" preserveAspectRatio="none" role="img" aria-label="7 day sales trend line chart">
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $padX + $drawW }}" y2="{{ $padY }}"></line>
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY + ($drawH / 2) }}" x2="{{ $padX + $drawW }}" y2="{{ $padY + ($drawH / 2) }}"></line>
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY + $drawH }}" x2="{{ $padX + $drawW }}" y2="{{ $padY + $drawH }}"></line>
            <path class="area-path" d="{{ $areaStr7 }}"></path>
            <polyline class="line-path" points="{{ $polyStr7 }}"></polyline>
            @foreach ($trend7 as $idx => $t)
              @php
                $coords = explode(',', $points7[$idx]);
              @endphp
              <circle class="point" cx="{{ $coords[0] }}" cy="{{ $coords[1] }}" r="4">
                <title>{{ $t['date'] }}: ${{ number_format($t['amount'], 2) }}</title>
              </circle>
            @endforeach
          </svg>
          <ul class="chart-axis">
            @foreach ($trend7 as $t)
              <li>{{ \Illuminate\Support\Carbon::parse($t['date'])->format('D j') }}</li>
            @endforeach
          </ul>
        </div>

                @php
          $max30 = max(array_merge([1.0], array_column($trend30, 'amount')));
          $n30 = count($trend30);
          $points30 = [];
          foreach ($trend30 as $idx => $t) {
              $x = $padX + ($n30 > 1 ? ($idx / ($n30 - 1)) * $drawW : $drawW / 2);
              $y = $padY + $drawH - (($t['amount'] / $max30) * $drawH);
              $points30[] = sprintf('%.1f,%.1f', $x, $y);
          }
          $polyStr30 = implode(' ', $points30);
          $areaStr30 = "M {$padX}," . ($padY + $drawH) . " L " . implode(' L ', $points30) . " L " . ($padX + $drawW) . "," . ($padY + $drawH) . " Z";
        @endphp
        <div data-trend-target="30" style="{{ $currentTrendDays === 30 ? '' : 'display:none' }}">
          <svg class="chart-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" preserveAspectRatio="none" role="img" aria-label="30 day sales trend line chart">
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY }}" x2="{{ $padX + $drawW }}" y2="{{ $padY }}"></line>
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY + ($drawH / 2) }}" x2="{{ $padX + $drawW }}" y2="{{ $padY + ($drawH / 2) }}"></line>
            <line class="grid-line" x1="{{ $padX }}" y1="{{ $padY + $drawH }}" x2="{{ $padX + $drawW }}" y2="{{ $padY + $drawH }}"></line>
            <path class="area-path" d="{{ $areaStr30 }}"></path>
            <polyline class="line-path" points="{{ $polyStr30 }}"></polyline>
            @foreach ($trend30 as $idx => $t)
              @if ($idx % 5 === 0 || $idx === $n30 - 1)
                @php $coords = explode(',', $points30[$idx]); @endphp
                <circle class="point" cx="{{ $coords[0] }}" cy="{{ $coords[1] }}" r="3.5">
                  <title>{{ $t['date'] }}: ${{ number_format($t['amount'], 2) }}</title>
                </circle>
              @endif
            @endforeach
          </svg>
          <ul class="chart-axis">
            <li>{{ \Illuminate\Support\Carbon::parse($trend30[0]['date'])->format('j M') }}</li>
            <li>{{ \Illuminate\Support\Carbon::parse($trend30[(int)($n30/3)]['date'])->format('j M') }}</li>
            <li>{{ \Illuminate\Support\Carbon::parse($trend30[(int)($n30*2/3)]['date'])->format('j M') }}</li>
            <li>{{ \Illuminate\Support\Carbon::parse($trend30[$n30-1]['date'])->format('j M') }}</li>
          </ul>
        </div>
      </article>

            <article class="card wide" data-testid="admin-chart-status">
        <header class="card-head">
          <div>
            <h3>Orders by status today</h3>
            <p class="card-sub">Distribution across order lifecycle states</p>
          </div>
        </header>

        @php
          $statusMax = max(array_merge([1], array_values($dashboard['orders_by_status'])));
          $statusColors = [
              'pending_payment' => 'var(--pending)',
              'paid' => 'var(--pending)',
              'preparing' => 'var(--preparing)',
              'ready' => 'var(--ready)',
              'served' => 'var(--served)',
              'cancelled' => 'var(--cancelled)',
          ];
          $statusLabels = [
              'pending_payment' => 'Pending payment',
              'paid' => 'Paid (unassigned)',
              'preparing' => 'Preparing in kitchen/bar',
              'ready' => 'Ready to serve',
              'served' => 'Served to table',
              'cancelled' => 'Cancelled',
          ];
        @endphp
        <div class="status-bars">
          @foreach ($dashboard['orders_by_status'] as $status => $count)
            @php
              $pct = ($count / $statusMax) * 100;
              $barColor = $statusColors[$status] ?? 'var(--cancelled)';
              $label = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
            @endphp
            <div class="status-bar-row">
              <span class="status-bar-label">{{ $label }}</span>
              <div class="status-bar-track">
                <div class="status-bar-fill" style="width: {{ $count > 0 ? max(3, $pct) : 0 }}%; background: {{ $barColor }}"></div>
              </div>
              <span class="status-bar-count">{{ $count }}</span>
            </div>
          @endforeach
        </div>
      </article>
    </div>

        <div class="dashboard-grid">
            <article class="card" style="grid-column: span 6" data-testid="admin-list-attention">
        <header class="card-head">
          <div>
            <h3>Needs attention</h3>
            <p class="card-sub">5 critical operational signals</p>
          </div>
          <span class="pill {{ $dashboard['needs_attention']['total'] > 0 ? '' : 'pill-quiet' }}">
            {{ $dashboard['needs_attention']['total'] }}
          </span>
        </header>

        <div class="attention-list">
          @if ($dashboard['needs_attention']['total'] === 0)
            <div class="attention-empty">
              &check; All clear — no active stock conflicts, late orders, or pending cash waits.
            </div>
          @else
                        @foreach ($dashboard['needs_attention']['stock_conflicts'] as $order)
              <div class="attention-item">
                <div class="attention-item-info">
                  <strong>Stock conflict: Order #{{ $order->order_number }}</strong>
                  <span>Table {{ $order->restaurantTable?->table_number ?? 'Counter' }} · Paid {{ $order->paid_at?->diffForHumans() }}</span>
                </div>
                <a class="btn btn-quiet" href="{{ route('admin.orders.show', $order) }}">Resolve</a>
              </div>
            @endforeach

                        @foreach ($dashboard['needs_attention']['refund_requests'] as $refund)
              <div class="attention-item warn">
                <div class="attention-item-info">
                  <strong>Refund requested: ${{ number_format($refund->amount, 2) }}</strong>
                  <span>Order #{{ $refund->order?->order_number }} · {{ $refund->reason }}</span>
                </div>
                <a class="btn btn-quiet" href="{{ route('admin.refunds.index') }}">Review</a>
              </div>
            @endforeach

                        @foreach ($dashboard['needs_attention']['cash_waiting'] as $payment)
              <div class="attention-item info">
                <div class="attention-item-info">
                  <strong>Cash payment waiting (&gt;10m)</strong>
                  <span>Table {{ $payment->order?->restaurantTable?->table_number ?? '?' }} · ${{ number_format($payment->amount, 2) }}</span>
                </div>
                <a class="btn btn-quiet" href="{{ route('staff.floor.index') }}">Floor</a>
              </div>
            @endforeach

                        @foreach ($dashboard['needs_attention']['unassigned_bookings'] as $res)
              <div class="attention-item warn">
                <div class="attention-item-info">
                  <strong>Unassigned booking (T&minus;30)</strong>
                  <span>{{ $res->party_size }} covers · {{ $res->customer?->full_name ?? $res->guest_name }} · {{ $res->booking_time }}</span>
                </div>
                <a class="btn btn-quiet" href="{{ route('staff.reservations.index') }}">Assign</a>
              </div>
            @endforeach

                        @foreach ($dashboard['needs_attention']['late_lines'] as $lateOrder)
              <div class="attention-item">
                <div class="attention-item-info">
                  <strong>Past ETA: Order #{{ $lateOrder->order_number }}</strong>
                  <span>Table {{ $lateOrder->restaurantTable?->table_number ?? 'Counter' }} · Prep delayed</span>
                </div>
                <a class="btn btn-quiet" href="{{ route('admin.orders.show', $lateOrder) }}">View</a>
              </div>
            @endforeach
          @endif
        </div>
      </article>

            <article class="card selling" style="grid-column: span 6" data-testid="admin-list-top-items">
        <header class="card-head">
          <div>
            <h3>Top 5 items today</h3>
            <p class="card-sub">By units sold & revenue</p>
          </div>
        </header>

        @if (empty($dashboard['top_items_today']))
          <div class="attention-empty">
            No item sales recorded today yet.
          </div>
        @else
          <ol class="rank">
            @foreach ($dashboard['top_items_today'] as $idx => $item)
              <li>
                <span class="rank-no">{{ $idx + 1 }}</span>
                <div class="rank-body">
                  <strong>{{ $item['item_name'] }}</strong>
                  <span>${{ number_format($item['revenue'], 2) }} gross revenue</span>
                </div>
                <span class="rank-count">{{ $item['quantity'] }} sold</span>
              </li>
            @endforeach
          </ol>
        @endif
      </article>

            <article class="card" style="grid-column: span 6" data-testid="admin-list-low-stock">
        <header class="card-head">
          <div>
            <h3>Low stock & sold out</h3>
            <p class="card-sub">Remaining &lt; 2&times; buffer or toggled unavailable</p>
          </div>
          <span class="pill pill-quiet">{{ count($dashboard['low_stock']) }}</span>
        </header>

        @if (empty($dashboard['low_stock']))
          <div class="attention-empty">
            &check; Healthy stock levels across all active items.
          </div>
        @else
          <div class="stock-list">
            @foreach ($dashboard['low_stock'] as $row)
              <div class="stock-item">
                <div class="stock-item-info">
                  <strong>{{ $row['item']->item_name }}</strong>
                  <span>Category: {{ $row['item']->category?->name ?? 'Menu' }}</span>
                </div>
                <div>
                  @if (! $row['is_available'])
                    <span class="badge b-cancelled">Sold out</span>
                  @else
                    <span class="badge b-preparing">{{ $row['remaining'] }} remaining</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </article>

            <article class="card" style="grid-column: span 6" data-testid="admin-list-feedback">
        <header class="card-head">
          <div>
            <h3>Latest feedback</h3>
            <p class="card-sub">5 most recent customer submissions</p>
          </div>
        </header>

        @if ($dashboard['latest_feedback']->isEmpty())
          <div class="attention-empty">
            No feedback submitted yet.
          </div>
        @else
          <div class="feedback-list">
            @foreach ($dashboard['latest_feedback'] as $fb)
              <div class="feedback-item">
                <div class="feedback-top">
                  <strong>{{ $fb->customer?->full_name ?? 'Guest' }}</strong>
                  <div class="feedback-stars">
                    <span>Food: {{ $fb->food_rating }}/5</span>
                    <span>·</span>
                    <span>Service: {{ $fb->service_rating }}/5</span>
                  </div>
                </div>
                @if ($fb->comment)
                  <p class="feedback-comment">&ldquo;{{ $fb->comment }}&rdquo;</p>
                @endif
                <p class="feedback-meta">
                  Order #{{ $fb->order?->order_number }} · {{ $fb->submitted_at?->diffForHumans() }}
                </p>
              </div>
            @endforeach
          </div>
        @endif
      </article>
    </div>
  </div>

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const toggleButtons = document.querySelectorAll('[data-trend-toggle]');
        toggleButtons.forEach(btn => {
          btn.addEventListener('click', function () {
            const days = this.dataset.trendToggle;
            document.querySelectorAll('[data-trend-target]').forEach(target => {
              target.style.display = target.dataset.trendTarget === days ? 'block' : 'none';
            });
            toggleButtons.forEach(b => {
              const active = b === btn;
              b.classList.toggle('is-on', active);
              b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
          });
        });
      });
    </script>
  @endpush
</x-layouts.admin>
