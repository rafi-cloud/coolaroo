@props([
    'active' => 'orders',
])

<nav class="customer-nav-tabs" aria-label="Account navigation" data-testid="customer-nav-tabs">
  <a href="{{ route('profile.edit') }}" class="tab-item {{ $active === 'profile' ? 'active' : '' }}" data-testid="tab-profile">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Profile</span>
  </a>
  <a href="{{ route('orders.index') }}" class="tab-item {{ $active === 'orders' ? 'active' : '' }}" data-testid="tab-orders">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    <span>My Orders</span>
  </a>
  <a href="{{ route('reservations.index') }}" class="tab-item {{ $active === 'reservations' ? 'active' : '' }}" data-testid="tab-reservations">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <span>My Reservations</span>
  </a>
  <form method="POST" action="{{ route('logout') }}" style="display:inline;margin-left:auto;">
    @csrf
    <button type="submit" class="tab-item tab-logout" data-testid="tab-logout">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Log out</span>
    </button>
  </form>
</nav>
