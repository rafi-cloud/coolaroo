<x-layouts.staff
  :title="'Refunds — table '.$table->table_number"
  page-title="Request a refund"
  :page-sub="'Table '.$table->table_number"
>
<div data-testid="staff-refunds-page">

  @if (session('status') === 'refund-requested')
    <div class="auth-error auth-success" role="status" data-testid="staff-refund-requested-notice">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Refund requested. A manager reviews it from the refund queue.</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <p>
    <a href="{{ route('staff.floor.index') }}" data-testid="staff-refunds-back">&larr; Back to the floor</a>
  </p>

  @forelse ($orders as $order)
    <section class="floor-list-panel" data-testid="staff-refund-order-{{ $order->order_id }}">
      <h2>#{{ $order->order_number }} &mdash; @money($order->total_amount)</h2>
      <p class="muted">Paid @auDateTime($order->paid_at)</p>

      <div class="table-scroll">
        <table class="table">
          <thead><tr><th>Item</th><th>Ordered</th><th>Refunds</th></tr></thead>
          <tbody>
            @foreach ($order->items as $item)
              <tr>
                <td>{{ $item->item_name }}<br><span class="muted">{{ $item->size_name }}</span></td>
                <td>{{ $item->quantity }}</td>
                <td>
                  @forelse ($item->refunds as $refund)
                    <x-staff.refund-progress :refund="$refund" />
                  @empty
                    <span class="muted" data-testid="refund-none-{{ $item->order_item_id }}">No refund on this line</span>
                  @endforelse
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if ($requestable[$order->order_id])
        <x-staff.refund-request :order="$order" />
      @else
        <p class="muted" data-testid="staff-refund-closed-{{ $order->order_id }}">
          Nothing left to request on this order &mdash; every line is already refunded or waiting on the manager.
        </p>
      @endif
    </section>
  @empty
    <section class="floor-list-panel">
      <p data-testid="staff-refunds-empty">
        No orders on this table were paid in the last {{ \App\Services\RefundService::REQUEST_WINDOW_HOURS }} hours, so there is nothing to refund here.
      </p>
    </section>
  @endforelse
</div>
</x-layouts.staff>
