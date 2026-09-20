@props(['order'])
<div class="modal" data-testid="refund-request-modal">
  <h2>Request refund — order #{{ $order->order_number }}</h2>

  <form method="POST" action="{{ route('staff.orders.refund-requests.store', $order) }}">
    @csrf

    <div class="auth-field">
      <label for="order_item_id">Line</label>
      <select id="order_item_id" name="order_item_id" required data-testid="refund-request-line">
        @foreach ($order->items as $item)
          <option value="{{ $item->order_item_id }}">{{ $item->item_name }} — {{ $item->size_name }} (qty {{ $item->quantity }})</option>
        @endforeach
      </select>
    </div>

    <div class="auth-field">
      <label for="quantity">Quantity</label>
      <input id="quantity" name="quantity" type="number" min="1" value="1" required data-testid="refund-request-quantity">
    </div>

    <div class="auth-field">
      <label for="reason">Reason</label>
      <input id="reason" name="reason" type="text" maxlength="255" required data-testid="refund-request-reason">
    </div>

    <button class="btn btn-orange" type="submit" data-testid="refund-request-submit">Submit request</button>
  </form>
</div>
