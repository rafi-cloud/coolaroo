@props(['table'])
@if (Route::has('table.call-waiter'))
  <form method="POST" action="{{ route('table.call-waiter', $table) }}">
    @csrf
    <button class="btn btn-orange" type="submit" data-testid="table-call-waiter">Call waiter</button>
  </form>
@else
  <p class="auth-foot" data-testid="table-call-waiter-pending">Need a hand? Please ask a staff member.</p>
@endif
