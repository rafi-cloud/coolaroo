<x-layouts.public title="Table reserved" description="This table is held for a reservation.">
<main class="auth">
  <div class="auth-card">
    <div class="auth-head">
      <img src="{{ asset('images/logo.svg') }}" alt="">
      <h1>Reserved for {{ $holderName }}</h1>
      <p>Table {{ $table->table_number }} is being held for a booking. A staff member can seat you at another table.</p>
    </div>

    <x-site.call-waiter :table="$table" />

    <p class="auth-foot"><a href="{{ url('/') }}" data-testid="table-reserved-home">Back to the homepage</a></p>
  </div>
</main>
</x-layouts.public>
