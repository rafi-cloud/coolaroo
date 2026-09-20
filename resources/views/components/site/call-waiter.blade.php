@props(['table'])
@if (Route::has('table.call-waiter'))
  @if (session('status') === 'waiter-called')
    <p class="auth-foot" role="status" data-testid="table-call-waiter-confirmed">A waiter is on the way.</p>
  @elseif (session('status') === 'waiter-cooldown')
    <p class="auth-foot" role="status" data-testid="table-call-waiter-cooldown">Already requested — a waiter is on the way.</p>
  @endif
  <form method="POST" action="{{ route('table.call-waiter', $table) }}">
    @csrf
    <button class="btn btn-orange" type="submit" data-testid="table-call-waiter">Call waiter</button>
  </form>
@else
  <p class="auth-foot" data-testid="table-call-waiter-pending">Need a hand? Please ask a staff member.</p>
@endif
