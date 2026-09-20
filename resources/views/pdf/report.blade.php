<!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<title>{{ $typeName }} — Coolaroo RMS</title>
<style>
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 11px;
    line-height: 1.4;
    color: #1e293b;
    padding: 24px;
  }
  .header {
    border-bottom: 2px solid #0f172a;
    padding-bottom: 14px;
    margin-bottom: 20px;
  }
  .title {
    font-size: 20px;
    font-weight: bold;
    color: #0f172a;
    margin: 0 0 4px 0;
  }
  .subtitle {
    font-size: 13px;
    color: #0284c7;
    margin: 0 0 4px 0;
    font-weight: 600;
  }
  .meta {
    font-size: 10px;
    color: #64748b;
    margin: 0;
  }
  .summary-grid {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
  }
  .summary-box {
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    padding: 10px 12px;
    vertical-align: top;
  }
  .summary-box .val {
    font-size: 16px;
    font-weight: bold;
    color: #0f172a;
    margin: 0 0 2px 0;
  }
  .summary-box .lbl {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin: 0;
  }
  .section-h {
    font-size: 13px;
    font-weight: bold;
    color: #0f172a;
    margin: 18px 0 8px 0;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 4px;
  }
  table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
  }
  table.data-table th, table.data-table td {
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    text-align: left;
    font-size: 10px;
  }
  table.data-table th {
    background-color: #f1f5f9;
    font-weight: 600;
    color: #334155;
    text-transform: uppercase;
    font-size: 9px;
    letter-spacing: 0.3px;
  }
  table.data-table tr:nth-child(even) td {
    background-color: #f8fafc;
  }
  table.data-table td.num, table.data-table th.num {
    text-align: right;
  }
  .footer {
    margin-top: 30px;
    border-top: 1px solid #e2e8f0;
    padding-top: 10px;
    font-size: 9px;
    color: #94a3b8;
    text-align: center;
  }
</style>
</head>
<body>
  <div class="header">
    <table style="width:100%; border:none; border-collapse:collapse;">
      <tr>
        <td style="vertical-align:top; border:none; padding:0;">
          <h1 class="title">{{ $typeName }}</h1>
          <p class="subtitle">{{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}</p>
          <p class="meta">Reporting Period: {{ $from->format('d/m/Y') }} to {{ $to->format('d/m/Y') }}</p>
        </td>
        <td style="vertical-align:top; text-align:right; border:none; padding:0;">
          <p class="meta">Generated: {{ $generatedAt->format('d/m/Y h:i A') }}</p>
          <p class="meta">{{ $venue['address'] ?? '412 Sydney Road, Coolaroo VIC 3048' }}</p>
          <p class="meta">Phone: {{ $venue['phone'] ?? '(03) 9300 0000' }}</p>
        </td>
      </tr>
    </table>
  </div>

  @if ($type === 'sales')
    <table class="summary-grid">
      <tr>
        <td class="summary-box" style="width:25%;">
          <p class="val">@money($data['gross_sales'])</p>
          <p class="lbl">Gross Sales</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">@money($data['net_sales'])</p>
          <p class="lbl">Net Takings</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">@money($data['gst_amount'])</p>
          <p class="lbl">GST Liability (1/11th)</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['total_orders'] }}</p>
          <p class="lbl">Paid Orders</p>
        </td>
      </tr>
      <tr>
        <td class="summary-box">
          <p class="val">@money($data['method_split']['cash_amount'])</p>
          <p class="lbl">Cash Takings</p>
        </td>
        <td class="summary-box">
          <p class="val">@money($data['method_split']['stripe_amount'])</p>
          <p class="lbl">Stripe Takings</p>
        </td>
        <td class="summary-box">
          <p class="val">@money($data['refunds'])</p>
          <p class="lbl">Total Refunds</p>
        </td>
        <td class="summary-box">
          <p class="val">@money($data['sale_discounts'] + $data['cash_adjustments'])</p>
          <p class="lbl">Discounts &amp; Adjustments</p>
        </td>
      </tr>
    </table>

    <h2 class="section-h">Daily Sales Breakdown</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th class="num">Orders Count</th>
          <th class="num">Gross Sales</th>
          <th class="num">GST Liability</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data['daily'] as $day)
          <tr>
            <td><strong>{{ $day['date'] }}</strong></td>
            <td class="num">{{ $day['count'] }}</td>
            <td class="num">@money($day['gross'])</td>
            <td class="num">@money($day['gst'])</td>
          </tr>
        @empty
          <tr>
            <td colspan="4" style="text-align:center; color:#94a3b8; padding:16px;">No transactions recorded in this period</td>
          </tr>
        @endforelse
      </tbody>
    </table>

  @elseif ($type === 'items')
    <h2 class="section-h">Top Selling Dishes Ranking</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Item Name</th>
          <th>Category</th>
          <th class="num">Qty Sold</th>
          <th class="num">Gross Revenue</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data['top_sellers'] as $idx => $item)
          <tr>
            <td>{{ $idx + 1 }}</td>
            <td><strong>{{ $item['item_name'] }}</strong></td>
            <td>{{ $item['category_name'] }}</td>
            <td class="num">{{ $item['quantity'] }}</td>
            <td class="num">@money($item['revenue'])</td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No item sales recorded</td></tr>
        @endforelse
      </tbody>
    </table>

    <h2 class="section-h">Category Sales Performance</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Category</th>
          <th class="num">Items Sold</th>
          <th class="num">Gross Revenue</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data['category_sales'] as $cat)
          <tr>
            <td><strong>{{ $cat['category_name'] }}</strong></td>
            <td class="num">{{ $cat['quantity'] }}</td>
            <td class="num">@money($cat['revenue'])</td>
          </tr>
        @empty
          <tr><td colspan="3" style="text-align:center; color:#94a3b8;">No category sales recorded</td></tr>
        @endforelse
      </tbody>
    </table>

  @elseif ($type === 'operations')
    <table class="summary-grid">
      <tr>
        <td class="summary-box" style="width:33.3%;">
          <p class="val">{{ $data['kitchen_on_time_pct'] !== null ? $data['kitchen_on_time_pct'].'%' : '—' }}</p>
          <p class="lbl">Kitchen On-Time Rate</p>
        </td>
        <td class="summary-box" style="width:33.3%;">
          <p class="val">{{ $data['bar_on_time_pct'] !== null ? $data['bar_on_time_pct'].'%' : '—' }}</p>
          <p class="lbl">Bar On-Time Rate</p>
        </td>
        <td class="summary-box" style="width:33.3%;">
          <p class="val">{{ $data['completed_visits_count'] }}</p>
          <p class="lbl">Completed Dining Visits</p>
        </td>
      </tr>
    </table>

    <h2 class="section-h">Average Dining Turnover by Party Size</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Party Size Tier</th>
          <th class="num">Average Turnover</th>
          <th>Standard Guideline</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>1–2 covers</strong></td>
          <td class="num">{{ $data['turnover_by_size']['1_2'] !== null ? $data['turnover_by_size']['1_2'].' min' : '—' }}</td>
          <td>90 minutes</td>
        </tr>
        <tr>
          <td><strong>3–6 covers</strong></td>
          <td class="num">{{ $data['turnover_by_size']['3_6'] !== null ? $data['turnover_by_size']['3_6'].' min' : '—' }}</td>
          <td>120 minutes</td>
        </tr>
        <tr>
          <td><strong>7+ covers</strong></td>
          <td class="num">{{ $data['turnover_by_size']['7_plus'] !== null ? $data['turnover_by_size']['7_plus'].' min' : '—' }}</td>
          <td>150 minutes</td>
        </tr>
      </tbody>
    </table>

    <h2 class="section-h">Orders by Hour (Peak Distribution)</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Hour</th>
          <th class="num">Orders Placed</th>
          <th class="num">Gross Sales</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($data['peak_hours'] as $hourData)
          <tr>
            <td>{{ sprintf('%02d:00 – %02d:59', $hourData['hour'], $hourData['hour']) }}</td>
            <td class="num">{{ $hourData['orders_count'] }}</td>
            <td class="num">@money($hourData['gross_sales'])</td>
          </tr>
        @endforeach
      </tbody>
    </table>

  @elseif ($type === 'reservations')
    <table class="summary-grid">
      <tr>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['total_bookings'] }}</p>
          <p class="lbl">Total Bookings</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['total_covers'] }}</p>
          <p class="lbl">Total Covers</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['approval_rate'] !== null ? $data['approval_rate'].'%' : '—' }}</p>
          <p class="lbl">Approval Rate</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['no_show_rate'] !== null ? $data['no_show_rate'].'%' : '0%' }}</p>
          <p class="lbl">No-Show Rate</p>
        </td>
      </tr>
      <tr>
        <td class="summary-box">
          <p class="val">{{ $data['no_show_count'] }}</p>
          <p class="lbl">No-Show Bookings</p>
        </td>
        <td class="summary-box">
          <p class="val">{{ $data['late_cancellations'] }}</p>
          <p class="lbl">Late Cancellations (&lt; 2h)</p>
        </td>
        <td class="summary-box" colspan="2">
          <p class="val">{{ $data['walk_in_visits'] }}</p>
          <p class="lbl">Walk-in Dining Visits</p>
        </td>
      </tr>
    </table>

    <h2 class="section-h">Reservation Status Distribution</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Status</th>
          <th class="num">Count</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($data['status_counts'] as $status => $count)
          <tr>
            <td><strong>{{ ucfirst($status) }}</strong></td>
            <td class="num">{{ $count }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

  @elseif ($type === 'feedback')
    <table class="summary-grid">
      <tr>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['overall_avg'] !== null ? $data['overall_avg'].' / 5' : '—' }}</p>
          <p class="lbl">Overall Average</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['food_avg'] !== null ? $data['food_avg'].' / 5' : '—' }}</p>
          <p class="lbl">Food Rating Average</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['service_avg'] !== null ? $data['service_avg'].' / 5' : '—' }}</p>
          <p class="lbl">Service Rating Average</p>
        </td>
        <td class="summary-box" style="width:25%;">
          <p class="val">{{ $data['total_reviews'] }}</p>
          <p class="lbl">Public Reviews Count</p>
        </td>
      </tr>
    </table>

    <h2 class="section-h">Recent Customer Reviews</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Customer</th>
          <th class="num">Food</th>
          <th class="num">Service</th>
          <th>Comment</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data['recent_reviews'] as $rev)
          <tr>
            <td>{{ $rev->submitted_at?->format('d/m/Y') }}</td>
            <td>{{ $rev->customer ? $rev->customer->first_name . ' ' . substr($rev->customer->last_name, 0, 1) . '.' : 'Guest Diner' }}</td>
            <td class="num">{{ $rev->food_rating }} ★</td>
            <td class="num">{{ $rev->service_rating }} ★</td>
            <td>{{ $rev->comment ?? 'No comment provided' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align:center; color:#94a3b8;">No customer reviews recorded</td></tr>
        @endforelse
      </tbody>
    </table>

  @elseif ($type === 'staff')
    <h2 class="section-h">Staff Performance &amp; Operations Activity</h2>
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Role</th>
          <th class="num">Cash Collected</th>
          <th class="num">Cash Total</th>
          <th class="num">Adjustments</th>
          <th class="num">Adjustments Total</th>
          <th class="num">Refund Requests</th>
          <th class="num">Menu Toggles</th>
          <th class="num">Overrides</th>
          <th class="num">Total Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($data['staff_activity'] as $row)
          <tr>
            <td><strong>{{ $row['staff']->name }}</strong></td>
            <td>{{ ucfirst($row['staff']->role?->role_name ?? 'staff') }}</td>
            <td class="num">{{ $row['cash_count'] }}</td>
            <td class="num">@money($row['cash_total'])</td>
            <td class="num">{{ $row['adjustments_count'] }}</td>
            <td class="num">@money($row['adjustments_total'])</td>
            <td class="num">{{ $row['refund_requests_count'] }}</td>
            <td class="num">{{ $row['toggles_count'] }}</td>
            <td class="num">{{ $row['overrides_count'] }}</td>
            <td class="num"><strong>{{ $row['total_actions'] }}</strong></td>
          </tr>
        @empty
          <tr><td colspan="10" style="text-align:center; color:#94a3b8;">No staff records found</td></tr>
        @endforelse
      </tbody>
    </table>
  @endif

  <div class="footer">
    <p>&copy; {{ date('Y') }} Coolaroo RMS &middot; Official Administrative Report &middot; Generated via Coolaroo Management System</p>
  </div>
</body>
</html>
