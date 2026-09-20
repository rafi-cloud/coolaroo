<x-layouts.public title="Table unavailable" description="This table is not available for ordering right now.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>This table isn't available</h1>
      <p>Table {{ $table->table_number }} isn't taking orders at the moment. A staff member can help you order.</p>
    </div>

    <x-site.call-waiter :table="$table" />

    <p class="auth-foot"><a href="{{ url('/') }}" data-testid="table-unavailable-home">Back to the homepage</a></p>
  </div>
</main>
</x-layouts.public>
