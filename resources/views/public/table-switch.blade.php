<x-layouts.public title="Start a new order?" description="Your cart belongs to another table.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Start a new order?</h1>
      <p>Your cart has {{ $cartCount }} {{ Str::plural('item', $cartCount) }} for table {{ $boundTable?->table_number ?? 'another table' }}. Ordering at table {{ $table->table_number }} will clear it.</p>
    </div>

    <form method="POST" action="{{ route('table.scan.switch', $table) }}">
      @csrf
      <button class="btn btn-orange" type="submit" data-testid="table-switch-confirm">Clear it and order here</button>
    </form>

    <p class="auth-foot"><a href="{{ route('cart.index') }}" data-testid="table-switch-keep">Keep my current order</a></p>
  </div>
</main>
</x-layouts.public>
