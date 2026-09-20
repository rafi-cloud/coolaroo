<!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<title>Receipt — Order #{{ $order->order_number }}</title>
<style>
  body { font-family: sans-serif; font-size: 13px; color: #222; padding: 30px; }
  h1 { font-size: 20px; margin-bottom: 2px; }
  h2 { font-size: 15px; margin-top: 24px; }
  .muted { color: #666; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #ddd; }
  th { font-size: 11px; text-transform: uppercase; color: #666; }
  .num { text-align: right; }
  .strike { text-decoration: line-through; color: #999; }
  tfoot td { border-bottom: none; font-weight: bold; }
  tfoot .label { text-align: right; }
</style>
</head>
<body>
  <h1>Coolaroo Restaurant &amp; Bistro</h1>
  <p class="muted">Receipt for order #{{ $order->order_number }}</p>
  <p class="muted">Placed @auDateTime($order->placed_at) — Table {{ $order->restaurantTable->table_number }}</p>

  <table>
    <thead>
      <tr><th>Item</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Line total</th></tr>
    </thead>
    <tbody>
      @foreach ($lines as $line)
        <tr>
          <td>
            {{ $line['item']->item_name }} — {{ $line['item']->size_name }}
            @if (! empty($line['item']->selected_options))
              <br><span class="muted">{{ collect($line['item']->selected_options)->pluck('name')->implode(', ') }}</span>
            @endif
            @if ($line['item']->special_request)
              <br><span class="muted">{{ $line['item']->special_request }}</span>
            @endif
          </td>
          <td class="num">{{ $line['item']->quantity }}</td>
          <td class="num">
            @if ($line['onSale'])
              <span class="strike">@money($line['item']->original_unit_price)</span>
            @endif
            @money($line['item']->unit_price)
          </td>
          <td class="num">@money($line['item']->line_total)</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="3" class="label">Total (GST inclusive)</td><td class="num">@money($order->total_amount)</td></tr>
      <tr><td colspan="3" class="label">Includes GST</td><td class="num">@money($order->gst_amount)</td></tr>
    </tfoot>
  </table>

  @if ($payment)
    <h2>Payment</h2>
    <p>Method: {{ ucfirst($payment->method->value) }}</p>
    @if ((float) $payment->rounding_amount !== 0.0)
      <p>Rounding: @money($payment->rounding_amount)</p>
    @endif
    @if ((float) $payment->adjustment_amount > 0)
      <p>Adjustment: -@money($payment->adjustment_amount) ({{ $payment->adjustment_category }}{{ $payment->adjustment_note ? ' — '.$payment->adjustment_note : '' }})</p>
    @endif
  @else
    <p class="muted">No payment recorded yet.</p>
  @endif

  @if ($refunds->isNotEmpty())
    <h2>Refunds</h2>
    <table>
      <thead><tr><th>Date</th><th>Reason</th><th class="num">Amount</th></tr></thead>
      <tbody>
        @foreach ($refunds as $refund)
          <tr>
            <td>@auDate($refund->completed_at)</td>
            <td>{{ $refund->reason }}</td>
            <td class="num">@money($refund->amount)</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</body>
</html>
