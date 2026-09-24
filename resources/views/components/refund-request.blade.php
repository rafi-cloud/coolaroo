@props(['order', 'lines'])
@php
  $modalId = 'refund-modal-'.$order->order_id;
  $reopen = $errors->hasAny(['order_item_id', 'quantity', 'reason', 'order'])
    && (int) old('refund_order_id') === (int) $order->order_id;
@endphp
<div
  class="modal-scrim {{ $reopen ? 'open' : '' }}"
  id="{{ $modalId }}"
  role="dialog"
  aria-modal="true"
  aria-labelledby="{{ $modalId }}-title"
  data-testid="refund-request-form"
>
  <div class="refund-modal">
    <div class="refund-modal-head">
      <h2 id="{{ $modalId }}-title">Request a refund</h2>
      <button type="button" class="refund-modal-close" data-close-modal="{{ $modalId }}" aria-label="Close" data-testid="refund-request-close-{{ $order->order_id }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
      </button>
    </div>

    <div class="refund-modal-body">
      <p class="refund-modal-sub">Order #{{ $order->order_number }} &middot; tell us which dish and what happened. A manager reviews every request — nothing is refunded automatically.</p>

      <form method="POST" action="{{ route('orders.refund-requests.store', $order) }}" class="refund-modal-form">
        @csrf
        <input type="hidden" name="refund_order_id" value="{{ $order->order_id }}">

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

        <div class="refund-modal-actions">
          <button type="button" class="btn btn-subtle" data-close-modal="{{ $modalId }}">Cancel</button>
          <button class="btn btn-orange" type="submit" data-testid="refund-request-submit">Request a refund</button>
        </div>
      </form>
    </div>
  </div>
</div>
