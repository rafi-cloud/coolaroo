<x-layouts.customer title="Your cart">
<div class="panel">
  @if (session('status') === 'order-placed')
    <div class="auth-error auth-success" role="status" data-testid="cart-order-placed">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Order #{{ session('order_number') }} placed — payment step is coming soon.</span>
    </div>
    @if (! empty(session('removed_items')))
      <p>Removed (no longer available): {{ implode(', ', session('removed_items')) }}</p>
    @endif
  @elseif (session('status'))
    <div class="auth-error auth-success" role="status">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>
      <span>Saved.</span>
    </div>
  @endif

  @if (session('error'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if ($errors->has('cart'))
    <div class="auth-error" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <span>{{ $errors->first('cart') }}</span>
    </div>
  @endif

  <h1>Your cart</h1>

  @forelse ($lines as $line)
    <div style="margin-bottom:1.2rem;border-bottom:1px solid #F0E6D8;padding-bottom:1rem">
      <strong>{{ $line['item']->item_name }}</strong> — {{ $line['size']->size_name }}
      @if ($line['options']->isNotEmpty())
        <p>{{ $line['options']->pluck('option_name')->implode(', ') }}</p>
      @endif

      <form method="POST" action="{{ route('cart.lines.update', $line['line_id']) }}">
        @csrf
        @method('PATCH')
        <input type="number" name="quantity" min="1" value="{{ $line['quantity'] }}" data-testid="cart-quantity-{{ $line['line_id'] }}">
        <input type="text" name="special_request" maxlength="200" value="{{ $line['special_request'] }}" placeholder="Special request" data-testid="cart-special-request-{{ $line['line_id'] }}">
        <button class="btn btn-orange" type="submit" data-testid="cart-update-{{ $line['line_id'] }}">Update</button>
      </form>
      <form method="POST" action="{{ route('cart.lines.destroy', $line['line_id']) }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline" type="submit" data-testid="cart-remove-{{ $line['line_id'] }}">Remove</button>
      </form>

      <p>@money($line['line_total'])</p>
    </div>
  @empty
    <p data-testid="cart-empty">Your cart is empty.</p>
  @endforelse

  @if (count($lines) > 0)
    <p><strong>Total: @money($total)</strong></p>
    <form method="POST" action="{{ route('cart.clear') }}">
      @csrf
      @method('DELETE')
      <button class="btn btn-outline" type="submit" data-testid="cart-clear">Clear cart</button>
    </form>
    <form method="POST" action="{{ route('checkout.store') }}">
      @csrf
      <button class="btn btn-orange" type="submit" data-testid="cart-checkout">Checkout</button>
    </form>
  @endif
</div>
</x-layouts.customer>
