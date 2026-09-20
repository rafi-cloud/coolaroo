<x-layouts.staff title="Reservations Board — Coolaroo">
  <div class="dashboard-page reservations-page" data-testid="reservations-board-page">
    <div class="page-header">
      <div class="header-titles">
        <h1>Reservations board</h1>
        <p class="text-muted">Manage guest bookings, review requests, and monitor table assignments.</p>
      </div>

      <div class="date-navigator" data-testid="reservations-date-nav">
        @php
          $currentCarbon = \Carbon\Carbon::parse($date);
          $prevDate = $currentCarbon->copy()->subDay()->toDateString();
          $nextDate = $currentCarbon->copy()->addDay()->toDateString();
          $todayDate = now()->toDateString();
        @endphp
        <a href="{{ route('staff.reservations.index', ['date' => $prevDate, 'status' => $statusFilter]) }}" class="btn btn-sm btn-outline" data-testid="reservations-prev-day">&larr; Previous</a>
        <form method="GET" action="{{ route('staff.reservations.index') }}" class="date-picker-form">
          @if ($statusFilter)
            <input type="hidden" name="status" value="{{ $statusFilter }}">
          @endif
          <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" data-testid="reservations-date-input" aria-label="Select booking date">
        </form>
        <a href="{{ route('staff.reservations.index', ['date' => $nextDate, 'status' => $statusFilter]) }}" class="btn btn-sm btn-outline" data-testid="reservations-next-day">Next &rarr;</a>
        @if ($date !== $todayDate)
          <a href="{{ route('staff.reservations.index', ['date' => $todayDate, 'status' => $statusFilter]) }}" class="btn btn-sm btn-ghost" data-testid="reservations-today-btn">Today</a>
        @endif
      </div>
    </div>

    @if (session('message'))
      <div class="alert alert-success" data-testid="reservations-success-alert">
        {{ session('message') }}
      </div>
    @endif

    <!-- Status filter navigation tabs -->
    <div class="filter-tabs" data-testid="reservations-filter-tabs">
      <a href="{{ route('staff.reservations.index', ['date' => $date]) }}" class="filter-pill {{ empty($statusFilter) ? 'active' : '' }}" data-testid="reservations-filter-all">
        All ({{ $counts['all'] }})
      </a>
      <a href="{{ route('staff.reservations.index', ['date' => $date, 'status' => 'requested']) }}" class="filter-pill {{ $statusFilter === 'requested' ? 'active' : '' }}" data-testid="reservations-filter-requested">
        Requested ({{ $counts['requested'] }})
      </a>
      <a href="{{ route('staff.reservations.index', ['date' => $date, 'status' => 'confirmed']) }}" class="filter-pill {{ $statusFilter === 'confirmed' ? 'active' : '' }}" data-testid="reservations-filter-confirmed">
        Confirmed ({{ $counts['confirmed'] }})
      </a>
      <a href="{{ route('staff.reservations.index', ['date' => $date, 'status' => 'seated']) }}" class="filter-pill {{ $statusFilter === 'seated' ? 'active' : '' }}" data-testid="reservations-filter-seated">
        Seated ({{ $counts['seated'] }})
      </a>
      <a href="{{ route('staff.reservations.index', ['date' => $date, 'status' => 'completed']) }}" class="filter-pill {{ $statusFilter === 'completed' ? 'active' : '' }}" data-testid="reservations-filter-completed">
        Completed ({{ $counts['completed'] }})
      </a>
      <a href="{{ route('staff.reservations.index', ['date' => $date, 'status' => 'cancelled']) }}" class="filter-pill {{ $statusFilter === 'cancelled' ? 'active' : '' }}" data-testid="reservations-filter-cancelled">
        Cancelled / Other ({{ $counts['cancelled'] }})
      </a>
    </div>

    <!-- Reservations list -->
    <div class="reservations-grid" data-testid="reservations-grid">
      @forelse ($reservations as $r)
        @php
          $statusValue = $r->status->value;
          $badgeClass = match ($r->trust_badge) {
              'Flagged' => 'badge-flagged',
              'Regular' => 'badge-regular',
              default => 'badge-new',
          };
          $statusClass = match ($r->status) {
              \App\Enums\ReservationStatus::Requested => 'b-pending',
              \App\Enums\ReservationStatus::Confirmed => 'b-preparing',
              \App\Enums\ReservationStatus::Seated => 'b-ready',
              \App\Enums\ReservationStatus::Completed => 'b-served',
              default => 'b-cancelled',
          };
        @endphp

        <div class="reservation-card {{ $r->is_unassigned_inside_t30 ? 'card-unassigned-t30' : '' }}" data-testid="reservations-card-{{ $r->reservation_id }}">
          @if ($r->is_unassigned_inside_t30)
            <div class="unassigned-t30-banner" data-testid="reservations-unassigned-t30-alert-{{ $r->reservation_id }}">
              ⚠️ Unassigned Booking (T–30 min)
            </div>
          @endif

          <div class="card-top">
            <div class="time-block">
              <span class="booking-time" data-testid="reservations-time-{{ $r->reservation_id }}">{{ substr($r->booking_time, 0, 5) }}</span>
              <span class="ref-code" data-testid="reservations-code-{{ $r->reservation_id }}">{{ $r->reference_code }}</span>
            </div>

            <div class="status-block">
              <span class="badge {{ $statusClass }}" data-testid="reservations-status-{{ $r->reservation_id }}">
                {{ ucfirst($statusValue) }}
              </span>
            </div>
          </div>

          <div class="card-guest-info">
            <div class="guest-identity">
              <strong class="guest-name" data-testid="reservations-guest-{{ $r->reservation_id }}">
                {{ $r->customer?->full_name ?? $r->guest_name ?? 'Guest' }}
              </strong>
              <span class="trust-badge {{ $badgeClass }}" data-testid="reservations-trust-badge-{{ $r->reservation_id }}">
                {{ $r->trust_badge }}
              </span>
            </div>
            <div class="guest-details muted">
              <span>{{ $r->party_size }} {{ \Illuminate\Support\Str::plural('guest', $r->party_size) }}</span>
              @if ($r->customer?->phone ?? $r->guest_phone)
                <span>&bull; {{ $r->customer?->phone ?? $r->guest_phone }}</span>
              @endif
            </div>
          </div>

          <!-- Table assignment info -->
          <div class="card-table-info">
            <strong>Table:</strong>
            @if ($r->assigned_tables->isNotEmpty())
              <span class="assigned-table-list" data-testid="reservations-tables-{{ $r->reservation_id }}">
                {{ $r->assigned_tables->pluck('table_number')->map(fn($n) => 'Table '.$n)->join(', ') }}
              </span>
            @else
              <span class="unassigned-tag {{ $r->is_unassigned_inside_t30 ? 'unassigned-urgent' : '' }}" data-testid="reservations-unassigned-tag-{{ $r->reservation_id }}">
                Unassigned
              </span>
            @endif
          </div>

          @if ($r->special_requests)
            <div class="card-requests" data-testid="reservations-requests-{{ $r->reservation_id }}">
              <span class="requests-label">Note:</span> &ldquo;{{ $r->special_requests }}&rdquo;
            </div>
          @endif

          <!-- Review panel for requested bookings (FR63, S28) -->
          @if ($r->status === \App\Enums\ReservationStatus::Requested)
            <div class="card-review-wrapper">
              <x-reservations.review :reservation="$r" />
            </div>
          @endif
        </div>
      @empty
        <div class="empty-state" data-testid="reservations-empty">
          <p class="muted">No reservations found for {{ \Carbon\Carbon::parse($date)->format('l, j F Y') }}.</p>
        </div>
      @endforelse
    </div>
  </div>
</x-layouts.staff>
