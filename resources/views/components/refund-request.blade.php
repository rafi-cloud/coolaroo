@props(['order', 'lines'])
<div class="order-refund-card" id="request-refund" data-testid="refund-request-form">
  <h2 class="order-refund-title">Something wrong with this order?</h2>
  <p class="order-refund-sub">Tell us which dish and what happened. A manager reviews every request — nothing is refunded automatically.</p>

  <form method="POST" action="{{ route('orders.refund-requests.store', $order) }}" class="order-refund-form">
    @csrf

    <div class="auth-field">
      <label for="refund-line-{{ $order->order_id }}">Which item</label>
      <select id="refund-line-{{ $order->order_id }}" name="order_item_id" required data-testid="refund-request-line">
        @foreach ($lines as $line)
          <option value="{{ $line['item']->order_item_id }}">
            {{ $line['item']->item_name }} — {{ $line['item']->size_name }} ({{ $line['remaining'] }} of {{ $line['item']->quantity }} left)
          </option>
        @endforeach
      </select>
      @error('order_item_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="refund-quantity-{{ $order->order_id }}">How many</label>
      <input id="refund-quantity-{{ $order->order_id }}" name="quantity" type="number" min="1" max="{{ $lines->max('remaining') }}" value="{{ old('quantity', 1) }}" required data-testid="refund-request-quantity">
      @error('quantity')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <div class="auth-field">
      <label for="refund-reason-{{ $order->order_id }}">What went wrong</label>
      <textarea id="refund-reason-{{ $order->order_id }}" name="reason" class="wizard-textarea" maxlength="255" required data-testid="refund-request-reason">{{ old('reason') }}</textarea>
      @error('reason')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    @error('order')<p class="field-error" role="alert">{{ $message }}</p>@enderror

    <button class="btn btn-outline" type="submit" data-testid="refund-request-submit">Request a refund</button>
  </form>
</div>
