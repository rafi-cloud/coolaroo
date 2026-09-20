@props(['order', 'qrSvg'])
<div class="modal" data-testid="stripe-qr-modal">
  <h2>Card payment — order #{{ $order->order_number }}</h2>
  <p>Ask the customer to scan this to pay by card on their phone.</p>

  <div class="qr">{!! $qrSvg !!}</div>

  <form method="POST" action="{{ route('staff.orders.payment-check', $order) }}">
    @csrf
    <button class="btn btn-orange" type="submit" data-testid="stripe-qr-check">Check payment status</button>
  </form>
</div>
