<x-layouts.admin title="Order #{{ $order->order_number }}" page-title="Order #{{ $order->order_number }}">
<div class="card">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Updated.</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <p>
    Table {{ $order->restaurantTable?->table_number ?? '—' }} &middot;
    <span class="badge b-{{ $order->status->value }}">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</span> &middot;
    @money($order->total_amount)
  </p>
  <p class="muted">
    {{ $order->customer ? $order->customer->full_name : 'Guest / staff-taken' }}
    @if ($order->takenBy) &middot; taken by {{ $order->takenBy->full_name }} @endif
  </p>

  @if ($order->has_stock_conflict)
    <form method="POST" action="{{ route('staff.orders.stock-conflict.resolve', $order) }}" style="margin:1rem 0">
      @csrf
      <label for="resolution">Stock conflict</label>
      <select id="resolution" name="resolution" data-testid="admin-order-conflict-resolution">
        <option value="will_make_it">Will make it</option>
        <option value="refund_request">Raise refund request</option>
      </select>
      <button type="submit" class="btn btn-ghost" data-testid="admin-order-conflict-resolve">Resolve conflict</button>
    </form>
  @endif

  @if ($order->status->value === 'pending_payment')
    <form method="POST" action="{{ route('staff.orders.cancel', $order) }}" style="margin:1rem 0">
      @csrf
      <label for="cancel-reason">Cancel reason</label>
      <input id="cancel-reason" name="reason" required data-testid="admin-order-cancel-reason">
      <button type="submit" class="btn btn-ghost" data-testid="admin-order-cancel">Cancel order</button>
    </form>
  @endif

  <h2>Lines</h2>
  <div class="table-scroll">
    <table class="table">
      <thead><tr><th>Item</th><th>Size</th><th>Qty</th><th>Unit price</th><th>Line total</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($order->items as $item)
          <tr>
            <td>{{ $item->item_name }}</td>
            <td>{{ $item->size_name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>@money($item->unit_price)</td>
            <td>@money($item->line_total)</td>
            <td><span class="badge b-{{ $item->status->value }}">{{ ucfirst($item->status->value) }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h2>Payments</h2>
  <div class="table-scroll">
    <table class="table">
      <thead><tr><th>Method</th><th>Amount</th><th>Status</th><th>Paid</th></tr></thead>
      <tbody>
        @forelse ($order->payments as $payment)
          <tr>
            <td>{{ ucfirst($payment->method->value) }}</td>
            <td>@money($payment->amount)</td>
            <td>{{ ucfirst($payment->status->value) }}</td>
            <td>@if ($payment->paid_at) @auDateTime($payment->paid_at) @else — @endif</td>
          </tr>
        @empty
          <tr><td colspan="4">No payment attempts.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2>Refunds</h2>
  <div class="table-scroll">
    <table class="table">
      <thead><tr><th>Line</th><th>Qty</th><th>Amount</th><th>Status</th><th>Requested by</th></tr></thead>
      <tbody>
        @forelse ($order->refunds as $refund)
          <tr>
            <td>{{ $refund->orderItem?->item_name ?? '—' }}</td>
            <td>{{ $refund->quantity }}</td>
            <td>@money($refund->amount)</td>
            <td>
              <span class="badge b-{{ $refund->status->value }}">{{ ucfirst($refund->status->value) }}</span>
              @if (in_array($refund->status->value, ['requested', 'processing'], true))
                <a href="{{ route('admin.refunds.index') }}">Queue</a>
              @endif
            </td>
            <td>{{ $refund->requestedBy->full_name }}</td>
          </tr>
        @empty
          <tr><td colspan="5">No refunds.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2>Status history</h2>
  <ul>
    @foreach ($order->statusHistory as $entry)
      <li>{{ ucfirst($entry->status) }} &mdash; @auDateTime($entry->occurred_at) ({{ $entry->event_source }})</li>
    @endforeach
  </ul>
</div>
</x-layouts.admin>
