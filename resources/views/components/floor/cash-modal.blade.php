@props(['order', 'amountDue', 'roundingAmount', 'stockWarning' => false])
<div class="modal" data-testid="cash-modal">
  <h2>Record cash payment — order #{{ $order->order_number }}</h2>

  @if ($stockWarning)
    <p data-testid="cash-modal-stock-warning">One or more items may be out of stock — the order will still be marked paid.</p>
  @endif

  <p>Amount due: @money($amountDue)</p>

  <form method="POST" action="{{ route('staff.orders.cash.store', $order) }}">
    @csrf

    <div class="auth-field">
      <label for="amount_received">Amount received</label>
      <input id="amount_received" name="amount_received" type="number" step="0.05" min="0" required data-testid="cash-modal-received">
    </div>

    <div class="auth-field">
      <label for="adjustment_amount">Adjustment (optional)</label>
      <input id="adjustment_amount" name="adjustment_amount" type="number" step="0.01" min="0" data-testid="cash-modal-adjustment-amount">
    </div>

    <div class="auth-field">
      <label for="adjustment_category">Adjustment category</label>
      <select id="adjustment_category" name="adjustment_category" data-testid="cash-modal-adjustment-category">
        <option value="">—</option>
        <option value="complaint">Complaint</option>
        <option value="staff_meal">Staff meal</option>
        <option value="manager_comp">Manager comp</option>
        <option value="other">Other</option>
      </select>
    </div>

    <div class="auth-field">
      <label for="adjustment_note">Adjustment note</label>
      <input id="adjustment_note" name="adjustment_note" type="text" maxlength="255" data-testid="cash-modal-adjustment-note">
    </div>

    <button class="btn btn-orange" type="submit" data-testid="cash-modal-confirm">Confirm payment</button>
  </form>
</div>
