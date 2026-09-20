<x-layouts.admin :title="'Reports · ' . $typeName" :page-title="'Reports · ' . $typeName" page-sub="Analytical Insights & Historic Performance">
  <div class="block">
    {{-- Navigation Tabs between the 6 Report Types --}}
    <nav class="report-nav" aria-label="Report Categories">
      @foreach ($types as $key => $label)
        <a class="report-nav-item {{ $type === $key ? 'is-active' : '' }}"
           href="{{ route('admin.reports.show', ['type' => $key, 'from' => $from, 'to' => $to]) }}"
           data-testid="admin-report-tab-{{ $key }}">
          {{ $label }}
        </a>
      @endforeach
    </nav>

    {{-- Date Filter Form & Presets --}}
    <div class="report-filter-bar">
      <form class="report-dates-form" method="GET" action="{{ route('admin.reports.show', $type) }}">
        <div class="date-input-group">
          <label for="report-from">From:</label>
          <input type="date" id="report-from" name="from" value="{{ $from }}" required>
        </div>
        <div class="date-input-group">
          <label for="report-to">To:</label>
          <input type="date" id="report-to" name="to" value="{{ $to }}" required>
        </div>
        <button class="btn btn-solid" type="submit" data-testid="admin-report-filter">Apply Filter</button>
      </form>

      <div class="preset-links">
        <span class="muted" style="font-size:0.75rem;margin-right:0.3rem">Presets:</span>
        <a class="btn btn-quiet" href="{{ route('admin.reports.show', ['type' => $type, 'from' => today()->toDateString(), 'to' => today()->toDateString()]) }}">Today</a>
        <a class="btn btn-quiet" href="{{ route('admin.reports.show', ['type' => $type, 'from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()]) }}">7 Days</a>
        <a class="btn btn-quiet" href="{{ route('admin.reports.show', ['type' => $type, 'from' => today()->subDays(29)->toDateString(), 'to' => today()->toDateString()]) }}">30 Days</a>
        <a class="btn btn-quiet" href="{{ route('admin.reports.show', ['type' => $type, 'from' => today()->startOfMonth()->toDateString(), 'to' => today()->endOfMonth()->toDateString()]) }}">This Month</a>
      </div>

      <div class="report-export-links" style="display:flex; gap:0.5rem; margin-left:auto;">
        <a class="btn btn-quiet" href="{{ route('admin.reports.export', ['type' => $type, 'format' => 'csv', 'from' => $from, 'to' => $to]) }}" data-testid="admin-report-export-csv">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          Export CSV
        </a>
        <a class="btn btn-quiet" href="{{ route('admin.reports.export', ['type' => $type, 'format' => 'pdf', 'from' => $from, 'to' => $to]) }}" data-testid="admin-report-export-pdf">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
          Export PDF
        </a>
      </div>
    </div>

    {{-- REPORT 1: SALES REPORT (FR82) --}}
    @if ($type === 'sales')
      <div class="dashboard-grid" data-testid="admin-report-sales">
        <article class="card tile">
          <p class="tile-value">${{ number_format($data['gross_sales'], 2) }}</p>
          <p class="tile-name">Gross sales (AUD)</p>
          <p class="tile-change"><span>{{ $data['total_orders'] }} paid orders in window</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">${{ number_format($data['gst_amount'], 2) }}</p>
          <p class="tile-name">GST inclusive (1/11th)</p>
          <p class="tile-change"><span>Australian Goods & Services Tax</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value" style="color:var(--pending)">-${{ number_format($data['refunds'], 2) }}</p>
          <p class="tile-name">Completed refunds</p>
          <p class="tile-change"><span>Discounts given: ${{ number_format($data['sale_discounts'], 2) }}</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value" style="color:var(--ready)">${{ number_format($data['net_sales'], 2) }}</p>
          <p class="tile-name">Net takings</p>
          <p class="tile-change"><span>Cash adjustments: ${{ number_format($data['cash_adjustments'], 2) }}</span></p>
        </article>
      </div>

      <div class="dashboard-grid">
        {{-- Payment Methods Split --}}
        <article class="card" style="grid-column: span 6">
          <header class="card-head">
            <div>
              <h3>Payment method split</h3>
              <p class="card-sub">Cash versus Stripe Checkout</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Method</th>
                  <th scope="col" class="num">Transactions</th>
                  <th scope="col" class="num">Total (AUD)</th>
                  <th scope="col" class="num">Share</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>Cash</strong></td>
                  <td class="num">{{ $data['method_split']['cash_count'] }}</td>
                  <td class="num">${{ number_format($data['method_split']['cash_amount'], 2) }}</td>
                  <td class="num">{{ $data['method_split']['cash_pct'] }}%</td>
                </tr>
                <tr>
                  <td><strong>Stripe Checkout</strong></td>
                  <td class="num">{{ $data['method_split']['stripe_count'] }}</td>
                  <td class="num">${{ number_format($data['method_split']['stripe_amount'], 2) }}</td>
                  <td class="num">{{ $data['method_split']['stripe_pct'] }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        {{-- Order Source Split --}}
        <article class="card" style="grid-column: span 6">
          <header class="card-head">
            <div>
              <h3>Order source breakdown</h3>
              <p class="card-sub">Customer self-service vs Staff order</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Source</th>
                  <th scope="col" class="num">Orders</th>
                  <th scope="col" class="num">Total (AUD)</th>
                  <th scope="col" class="num">Share</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>QR Customer Self-Order</strong></td>
                  <td class="num">{{ $data['source_split']['qr_count'] }}</td>
                  <td class="num">${{ number_format($data['source_split']['qr_amount'], 2) }}</td>
                  <td class="num">{{ $data['source_split']['qr_pct'] }}%</td>
                </tr>
                <tr>
                  <td><strong>Staff Table Order (POS)</strong></td>
                  <td class="num">{{ $data['source_split']['staff_count'] }}</td>
                  <td class="num">${{ number_format($data['source_split']['staff_amount'], 2) }}</td>
                  <td class="num">{{ $data['source_split']['staff_pct'] }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        {{-- Cash by Staff --}}
        <article class="card wide" data-testid="admin-report-cash-staff">
          <header class="card-head">
            <div>
              <h3>Cash takings by staff member</h3>
              <p class="card-sub">Cash received and rounded per staff login</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Staff Name</th>
                  <th scope="col">Role</th>
                  <th scope="col" class="num">Cash Transactions</th>
                  <th scope="col" class="num">Total Collected (AUD)</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['cash_by_staff'] as $staff)
                  <tr>
                    <td><strong>{{ $staff['staff_name'] }}</strong></td>
                    <td><span class="tag tag-quiet">{{ ucfirst($staff['role']) }}</span></td>
                    <td class="num">{{ $staff['count'] }}</td>
                    <td class="num"><strong>${{ number_format($staff['total'], 2) }}</strong></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="muted" style="text-align:center">No cash payments recorded in this period.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        {{-- Daily Breakdown --}}
        <article class="card wide" data-testid="admin-report-daily">
          <header class="card-head">
            <div>
              <h3>Daily sales breakdown</h3>
              <p class="card-sub">Gross, GST and transaction counts by calendar day</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col" class="num">Paid Orders</th>
                  <th scope="col" class="num">Gross (AUD)</th>
                  <th scope="col" class="num">GST (AUD)</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['daily'] as $day)
                  <tr>
                    <td class="mono">{{ $day['date'] }}</td>
                    <td class="num">{{ $day['count'] }}</td>
                    <td class="num"><strong>${{ number_format($day['gross'], 2) }}</strong></td>
                    <td class="num">${{ number_format($day['gst'], 2) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="muted" style="text-align:center">No sales recorded in this period.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif

    {{-- REPORT 2: ITEMS & CATEGORIES (FR83) --}}
    @if ($type === 'items')
      <div class="dashboard-grid" data-testid="admin-report-items">
        <article class="card tile">
          <p class="tile-value">{{ $data['total_items_sold'] }}</p>
          <p class="tile-name">Total items sold</p>
          <p class="tile-change"><span>Across all menu lines</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">${{ number_format($data['total_revenue'], 2) }}</p>
          <p class="tile-name">Gross item revenue</p>
          <p class="tile-change"><span>From paid orders in window</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['toggle_count'] }}</p>
          <p class="tile-name">Availability toggles</p>
          <p class="tile-change"><span>Station & admin switches</span></p>
        </article>
      </div>

      <div class="dashboard-grid">
        {{-- Top Sellers --}}
        <article class="card" style="grid-column: span 6" data-testid="admin-report-top-items">
          <header class="card-head">
            <div>
              <h3>Top 10 selling items</h3>
              <p class="card-sub">Ranked by volume sold</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Dish / Item</th>
                  <th scope="col">Category</th>
                  <th scope="col" class="num">Qty</th>
                  <th scope="col" class="num">Revenue</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['top_sellers'] as $idx => $item)
                  <tr>
                    <td><span class="rank-no">{{ $idx + 1 }}</span></td>
                    <td><strong>{{ $item['item_name'] }}</strong></td>
                    <td><span class="tag tag-quiet">{{ $item['category_name'] }}</span></td>
                    <td class="num"><strong>{{ $item['quantity'] }}</strong></td>
                    <td class="num">${{ number_format($item['revenue'], 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="muted" style="text-align:center">No item sales recorded in this window.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        {{-- Bottom Sellers --}}
        <article class="card" style="grid-column: span 6" data-testid="admin-report-bottom-items">
          <header class="card-head">
            <div>
              <h3>Lowest performing active items</h3>
              <p class="card-sub">Active items needing promotion or review</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Dish / Item</th>
                  <th scope="col">Category</th>
                  <th scope="col" class="num">Qty</th>
                  <th scope="col" class="num">Revenue</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['bottom_sellers'] as $item)
                  <tr>
                    <td><strong>{{ $item['item_name'] }}</strong></td>
                    <td><span class="tag tag-quiet">{{ $item['category_name'] }}</span></td>
                    <td class="num">{{ $item['quantity'] }}</td>
                    <td class="num">${{ number_format($item['revenue'], 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="muted" style="text-align:center">No items found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        {{-- Category Sales Breakdown --}}
        <article class="card" style="grid-column: span 7" data-testid="admin-report-category-sales">
          <header class="card-head">
            <div>
              <h3>Category performance</h3>
              <p class="card-sub">Revenue and volume per menu category</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Category</th>
                  <th scope="col" class="num">Units Sold</th>
                  <th scope="col" class="num">Total Revenue</th>
                  <th scope="col" class="num">Share of Sales</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['category_sales'] as $cat)
                  @php
                    $share = $data['total_revenue'] > 0 ? round(($cat['revenue'] / $data['total_revenue']) * 100, 1) : 0;
                  @endphp
                  <tr>
                    <td><strong>{{ $cat['category_name'] }}</strong></td>
                    <td class="num">{{ $cat['quantity'] }}</td>
                    <td class="num"><strong>${{ number_format($cat['revenue'], 2) }}</strong></td>
                    <td class="num">{{ $share }}%</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="muted" style="text-align:center">No category sales recorded.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        {{-- Sold-Out Items Status --}}
        <article class="card" style="grid-column: span 5" data-testid="admin-report-stock-status">
          <header class="card-head">
            <div>
              <h3>Currently unavailable items</h3>
              <p class="card-sub">Active items marked sold out</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Item</th>
                  <th scope="col">Category</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['sold_out_items'] as $item)
                  <tr>
                    <td><strong>{{ $item->item_name }}</strong></td>
                    <td>{{ $item->category?->category_name ?? 'Menu' }}</td>
                    <td><span class="badge b-cancelled">Sold out</span></td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="muted" style="text-align:center">All active menu items are currently available.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif

    {{-- REPORT 3: OPERATIONS (FR84) --}}
    @if ($type === 'operations')
      <div class="dashboard-grid" data-testid="admin-report-operations">
        <article class="card tile">
          <p class="tile-value">{{ $data['kitchen_avg_prep'] !== null ? $data['kitchen_avg_prep'].'m' : '—' }}</p>
          <p class="tile-name">Kitchen avg prep time</p>
          <p class="tile-change"><span>On-time rate: {{ $data['kitchen_on_time_pct'] !== null ? $data['kitchen_on_time_pct'].'%' : '—' }}</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['bar_avg_prep'] !== null ? $data['bar_avg_prep'].'m' : '—' }}</p>
          <p class="tile-name">Bar avg prep time</p>
          <p class="tile-change"><span>On-time rate: {{ $data['bar_on_time_pct'] !== null ? $data['bar_on_time_pct'].'%' : '—' }}</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['avg_turnover_minutes'] !== null ? $data['avg_turnover_minutes'].'m' : '—' }}</p>
          <p class="tile-name">Avg table turnover</p>
          <p class="tile-change"><span>Across {{ $data['completed_visits_count'] }} completed visits</span></p>
        </article>
      </div>

      <div class="dashboard-grid">
        {{-- Peak Hours Breakdown --}}
        <article class="card wide" data-testid="admin-report-peak-hours">
          <header class="card-head">
            <div>
              <h3>Peak operational hours</h3>
              <p class="card-sub">Order volume and revenue by hour of the day (00:00 – 23:00)</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Hour Window</th>
                  <th scope="col" class="num">Orders Placed</th>
                  <th scope="col" class="num">Gross Revenue (AUD)</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($data['peak_hours'] as $hour)
                  @if ($hour['orders_count'] > 0)
                    <tr>
                      <td class="mono">{{ sprintf('%02d:00 – %02d:59', $hour['hour'], $hour['hour']) }}</td>
                      <td class="num"><strong>{{ $hour['orders_count'] }}</strong></td>
                      <td class="num">${{ number_format($hour['gross_sales'], 2) }}</td>
                    </tr>
                  @endif
                @endforeach
                @if (collect($data['peak_hours'])->sum('orders_count') === 0)
                  <tr><td colspan="3" class="muted" style="text-align:center">No order traffic recorded in this range.</td></tr>
                @endif
              </tbody>
            </table>
          </div>
        </article>

        {{-- Table Turnover Details --}}
        <article class="card wide" data-testid="admin-report-table-turnover">
          <header class="card-head">
            <div>
              <h3>Table turnover & duration by party size</h3>
              <p class="card-sub">Actual dining duration calculated from opened_at to closed_at visit timestamps</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Party Size Range</th>
                  <th scope="col">Standard Turn Time Policy (BR34)</th>
                  <th scope="col" class="num">Observed Average Duration</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>Small Tables (1–2 covers)</strong></td>
                  <td>90 minutes</td>
                  <td class="num"><strong>{{ $data['turnover_by_size']['1_2'] !== null ? $data['turnover_by_size']['1_2'].' minutes' : '—' }}</strong></td>
                </tr>
                <tr>
                  <td><strong>Medium Tables (3–6 covers)</strong></td>
                  <td>120 minutes</td>
                  <td class="num"><strong>{{ $data['turnover_by_size']['3_6'] !== null ? $data['turnover_by_size']['3_6'].' minutes' : '—' }}</strong></td>
                </tr>
                <tr>
                  <td><strong>Large Tables (7+ covers)</strong></td>
                  <td>150 minutes</td>
                  <td class="num"><strong>{{ $data['turnover_by_size']['7_plus'] !== null ? $data['turnover_by_size']['7_plus'].' minutes' : '—' }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif

    {{-- REPORT 4: RESERVATIONS (FR85) --}}
    @if ($type === 'reservations')
      <div class="dashboard-grid" data-testid="admin-report-reservations">
        <article class="card tile">
          <p class="tile-value">{{ $data['total_bookings'] }}</p>
          <p class="tile-name">Total bookings</p>
          <p class="tile-change"><span>{{ $data['total_covers'] }} total dining covers</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['approval_rate'] !== null ? $data['approval_rate'].'%' : '—' }}</p>
          <p class="tile-name">Request approval rate</p>
          <p class="tile-change"><span>Staff confirmed vs declined</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value" style="color: {{ $data['no_show_count'] > 0 ? 'var(--pending)' : 'var(--ready)' }}">
            {{ $data['no_show_rate'] !== null ? $data['no_show_rate'].'%' : '0%' }}
          </p>
          <p class="tile-name">No-show rate</p>
          <p class="tile-change"><span>{{ $data['no_show_count'] }} recorded no-shows</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['walk_in_visits'] }}</p>
          <p class="tile-name">Walk-in dining visits</p>
          <p class="tile-change"><span>Late cancellations: {{ $data['late_cancellations'] }}</span></p>
        </article>
      </div>

      <div class="dashboard-grid">
        <article class="card wide" data-testid="admin-report-res-status">
          <header class="card-head">
            <div>
              <h3>Reservation lifecycle status distribution</h3>
              <p class="card-sub">Summary across all booking states</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Status</th>
                  <th scope="col" class="num">Bookings Count</th>
                  <th scope="col">Operational Meaning</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($data['status_counts'] as $status => $count)
                  <tr>
                    <td><span class="badge b-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span></td>
                    <td class="num"><strong>{{ $count }}</strong></td>
                    <td class="muted">
                      @if ($status === 'requested') Awaiting staff review
                      @elseif ($status === 'confirmed') Confirmed table allocated
                      @elseif ($status === 'seated') Currently dining in venue
                      @elseif ($status === 'completed') Successfully completed visit
                      @elseif ($status === 'declined') Declined due to capacity/hours
                      @elseif ($status === 'cancelled') Cancelled before grace period
                      @elseif ($status === 'no_show') Did not arrive within 15 min grace
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif

    {{-- REPORT 5: FEEDBACK (FR86) --}}
    @if ($type === 'feedback')
      <div class="dashboard-grid" data-testid="admin-report-feedback">
        <article class="card tile">
          <p class="tile-value">{{ $data['overall_avg'] !== null ? $data['overall_avg'] : '—' }}<span style="font-size:1rem;color:var(--cancelled)">/5</span></p>
          <p class="tile-name">Overall venue rating</p>
          <p class="tile-change"><span>Across {{ $data['total_reviews'] }} valid reviews</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['food_avg'] !== null ? $data['food_avg'] : '—' }}<span style="font-size:1rem;color:var(--cancelled)">/5</span></p>
          <p class="tile-name">Food quality average</p>
          <p class="tile-change"><span>Kitchen & bar culinary rating</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value">{{ $data['service_avg'] !== null ? $data['service_avg'] : '—' }}<span style="font-size:1rem;color:var(--cancelled)">/5</span></p>
          <p class="tile-name">Service quality average</p>
          <p class="tile-change"><span>Floor & hospitality rating</span></p>
        </article>

        <article class="card tile">
          <p class="tile-value" style="color:var(--cancelled)">{{ $data['hidden_count'] }}</p>
          <p class="tile-name">Hidden feedback count</p>
          <p class="tile-change"><span>Moderated abusive or spam (FR78)</span></p>
        </article>
      </div>

      <div class="dashboard-grid" data-testid="admin-report-feedback-dist">
        {{-- Food Rating Distribution --}}
        <article class="card" style="grid-column: span 6">
          <header class="card-head">
            <div>
              <h3>Food rating distribution</h3>
              <p class="card-sub">Stars breakdown (1 to 5)</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Rating</th>
                  <th scope="col" class="num">Reviews</th>
                  <th scope="col" class="num">Percentage</th>
                </tr>
              </thead>
              <tbody>
                @foreach (array_reverse($data['food_distribution'], true) as $star => $dist)
                  <tr>
                    <td><strong>{{ $star }} ★</strong></td>
                    <td class="num">{{ $dist['count'] }}</td>
                    <td class="num">{{ $dist['pct'] }}%</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </article>

        {{-- Service Rating Distribution --}}
        <article class="card" style="grid-column: span 6">
          <header class="card-head">
            <div>
              <h3>Service rating distribution</h3>
              <p class="card-sub">Stars breakdown (1 to 5)</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Rating</th>
                  <th scope="col" class="num">Reviews</th>
                  <th scope="col" class="num">Percentage</th>
                </tr>
              </thead>
              <tbody>
                @foreach (array_reverse($data['service_distribution'], true) as $star => $dist)
                  <tr>
                    <td><strong>{{ $star }} ★</strong></td>
                    <td class="num">{{ $dist['count'] }}</td>
                    <td class="num">{{ $dist['pct'] }}%</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </article>

        {{-- Recent Customer Reviews --}}
        <article class="card wide" data-testid="admin-report-feedback-recent">
          <header class="card-head">
            <div>
              <h3>Recent customer feedback</h3>
              <p class="card-sub">Customer commentary and ratings submitted in this window</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Customer</th>
                  <th scope="col">Order</th>
                  <th scope="col" class="num">Food</th>
                  <th scope="col" class="num">Service</th>
                  <th scope="col">Comment</th>
                  <th scope="col">Date</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['recent_reviews'] as $fb)
                  <tr>
                    <td><strong>{{ $fb->customer?->full_name ?? 'Guest' }}</strong></td>
                    <td class="mono">#{{ $fb->order?->order_number }}</td>
                    <td class="num">{{ $fb->food_rating }} ★</td>
                    <td class="num">{{ $fb->service_rating }} ★</td>
                    <td><em>{{ $fb->comment ?? '—' }}</em></td>
                    <td>{{ $fb->submitted_at?->format('d M Y, H:i') }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="muted" style="text-align:center">No customer reviews submitted in this date range.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif

    {{-- REPORT 6: STAFF ACTIVITY (FR87) --}}
    @if ($type === 'staff')
      <div class="dashboard-grid" data-testid="admin-report-staff-activity">
        <article class="card wide">
          <header class="card-head">
            <div>
              <h3>Staff operational activity report</h3>
              <p class="card-sub">Cash handling, adjustments, refund requests, stock toggles and table overrides per staff member</p>
            </div>
          </header>
          <div class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Staff Member</th>
                  <th scope="col">Role</th>
                  <th scope="col" class="num">Cash Payments</th>
                  <th scope="col" class="num">Cash Total</th>
                  <th scope="col" class="num">Adjustments</th>
                  <th scope="col" class="num">Refund Requests</th>
                  <th scope="col" class="num">Stock Toggles</th>
                  <th scope="col" class="num">Overrides</th>
                  <th scope="col" class="num">Total Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($data['staff_activity'] as $row)
                  <tr>
                    <td>
                      <strong>{{ $row['staff']->name }}</strong>
                      <div class="muted" style="font-size:0.75rem">{{ $row['staff']->email }}</div>
                    </td>
                    <td><span class="tag tag-quiet">{{ ucfirst($row['staff']->role?->role_name ?? 'Staff') }}</span></td>
                    <td class="num">{{ $row['cash_count'] }}</td>
                    <td class="num">${{ number_format($row['cash_total'], 2) }}</td>
                    <td class="num">{{ $row['adjustments_count'] }} (${{ number_format($row['adjustments_total'], 2) }})</td>
                    <td class="num">{{ $row['refund_requests_count'] }}</td>
                    <td class="num">{{ $row['toggles_count'] }}</td>
                    <td class="num">{{ $row['overrides_count'] }}</td>
                    <td class="num"><strong>{{ $row['total_actions'] }}</strong></td>
                  </tr>
                @empty
                  <tr><td colspan="9" class="muted" style="text-align:center">No staff members found.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>
      </div>
    @endif
  </div>
</x-layouts.admin>
