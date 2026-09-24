<form class="nav-foot" method="POST" action="{{ route('staff.logout') }}">
  @csrf
  <button type="submit" class="nav-item nav-logout" aria-label="Log out" data-testid="staff-logout">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    <span>Log out</span>
  </button>
</form>
