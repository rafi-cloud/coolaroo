@props(['order'])
@php($lines = $order->items->map(fn ($item) => [
  'item' => $item,
  'remaining' => app(\App\Services\RefundService::class)->remainingQuantity($item),
])->filter(fn ($line) => $line['remaining'] > 0)->values())
<div class="modal" data-testid="refund-request-modal">
  <h2>Request refund — order #{{ $order->order_number }}</h2>

  <form method="POST" action="{{ route('staff.orders.refund-requests.store', $order) }}">
    @csrf

    <div class="auth-field">
      <label for="order_item_id-{{ $order->order_id }}">Line</label>
      <select id="order_item_id-{{ $order->order_id }}" name="order_item_id" required data-testid="refund-request-line">
        @foreach ($lines as $line)
          <option value="{{ $line['item']->order_item_id }}">
            {{ $line['item']->item_name }} — {{ $line['item']->size_name }} ({{ $line['remaining'] }} of {{ $line['item']->quantity }} left)
          </option>
        @endforeach
      </select>
    </div>

    <div class="auth-field">
      <label for="quantity-{{ $order->order_id }}">How many units</label>
      <input id="quantity-{{ $order->order_id }}" name="quantity" type="number" min="1" max="{{ $lines->max('remaining') }}" value="1" required data-testid="refund-request-quantity">
      <p class="muted">A line can be refunded in part — one of three beers, say. Leave it at 1 for a single dish.</p>
    </div>

    <div class="auth-field">
      <label for="reason-{{ $order->order_id }}">Reason</label>
      <input id="reason-{{ $order->order_id }}" name="reason" type="text" maxlength="255" required data-testid="refund-request-reason">
    </div>

    <button class="btn btn-orange" type="submit" data-testid="refund-request-submit">Submit request</button>
  </form>
</div>
