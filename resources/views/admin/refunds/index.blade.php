<x-layouts.admin title="Refunds" page-title="Refund queue">
<div class="card">
  @if (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>
        @switch(session('status'))
          @case('refund-approved') Refund approved. @break
          @case('refund-rejected') Refund rejected. @break
          @case('refund-checked') Checked with Stripe. @break
          @case('refund-retried') Refund retried. @break
        @endswitch
      </span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form method="GET" action="{{ route('admin.refunds.index') }}" class="refund-action">
    <label for="refund-status-filter">Status</label>
    <select id="refund-status-filter" name="status" data-testid="admin-refunds-filter-status">
      <option value="">All</option>
      @foreach ($statuses as $case)
        <option value="{{ $case->value }}" @selected($status === $case->value)>{{ ucfirst($case->value) }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-ghost" data-testid="admin-refunds-filter-submit">Filter</button>
  </form>

  <div class="table-scroll" style="margin-top:1.2rem">
    <table class="table">
      <thead>
        <tr>
          <th>Requested</th>
          <th>Order</th>
          <th>Line</th>
          <th>Qty</th>
          <th>Amount</th>
          <th>Reason</th>
          <th>By</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($refunds as $refund)
          <tr>
            <td>@auDateTime($refund->requested_at)</td>
            <td>{{ $refund->order->order_number }}</td>
            <td>{{ $refund->orderItem?->item_name ?? '—' }}</td>
            <td>{{ $refund->quantity }}</td>
            <td>@money($refund->amount)</td>
            <td>{{ $refund->reason }}</td>
            <td>{{ $refund->requestedBy->full_name }}</td>
            <td>
              <span class="badge b-{{ $refund->status->value }}" data-testid="admin-refund-status-{{ $refund->refund_id }}">{{ ucfirst($refund->status->value) }}</span>
              @if ($refund->status === \App\Enums\RefundStatus::Rejected && $refund->rejection_reason)
                <span class="muted">{{ $refund->rejection_reason }}</span>
              @endif
            </td>
            <td>
              @if ($refund->status === \App\Enums\RefundStatus::Requested)
                <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}" class="refund-action">
                  @csrf
                  @method('PATCH')
                  <label class="sr-only" for="method-{{ $refund->refund_id }}">Refund method</label>
                  <select id="method-{{ $refund->refund_id }}" name="method" data-testid="admin-refund-method-{{ $refund->refund_id }}">
                    @if ($refund->payment?->stripe_session_id)
                      <option value="stripe" @selected($refund->method->value === 'stripe')>Stripe</option>
                    @endif
                    <option value="cash" @selected($refund->method->value === 'cash')>Cash</option>
                    <option value="manual">Manual</option>
                  </select>
                  <label class="sr-only" for="reference-{{ $refund->refund_id }}">Manual reference</label>
                  <input id="reference-{{ $refund->refund_id }}" name="manual_reference" placeholder="Manual reference" maxlength="100" data-testid="admin-refund-reference-{{ $refund->refund_id }}">
                  <label for="stock-{{ $refund->refund_id }}">
                    <input type="checkbox" id="stock-{{ $refund->refund_id }}" name="return_to_stock" value="1" data-testid="admin-refund-stock-{{ $refund->refund_id }}">
                    Return to stock
                  </label>
                  <button type="submit" class="btn btn-solid" data-testid="admin-refund-approve-{{ $refund->refund_id }}">Approve</button>
                </form>

                <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}" class="refund-action">
                  @csrf
                  @method('PATCH')
                  <label class="sr-only" for="rejection-{{ $refund->refund_id }}">Rejection reason</label>
                  <input id="rejection-{{ $refund->refund_id }}" name="rejection_reason" placeholder="Rejection reason" maxlength="255" required data-testid="admin-refund-rejection-{{ $refund->refund_id }}">
                  <button type="submit" class="btn btn-ghost" data-testid="admin-refund-reject-{{ $refund->refund_id }}">Reject</button>
                </form>
              @elseif ($refund->status === \App\Enums\RefundStatus::Processing)
                <form method="POST" action="{{ route('admin.refunds.complete', $refund) }}" class="refund-action">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-refund-complete-{{ $refund->refund_id }}">Check with Stripe</button>
                </form>
              @elseif ($refund->status === \App\Enums\RefundStatus::Failed)
                <form method="POST" action="{{ route('admin.refunds.retry', $refund) }}" class="refund-action">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-solid" data-testid="admin-refund-retry-{{ $refund->refund_id }}">Retry</button>
                </form>
              @else
                <span class="muted">{{ $refund->processedBy?->full_name ?? '—' }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="9">No refunds.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
</x-layouts.admin>
