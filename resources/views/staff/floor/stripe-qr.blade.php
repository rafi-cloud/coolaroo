<x-layouts.staff title="Card payment QR" page-title="Card payment QR">
  @if (session('error'))
    <p data-testid="stripe-qr-error">{{ session('error') }}</p>
  @endif
  @if (session('status') === 'order-paid')
    <p data-testid="stripe-qr-paid">Payment received.</p>
  @endif

  <x-floor.stripe-qr :order="$order" :qr-svg="$qrSvg" />
</x-layouts.staff>
