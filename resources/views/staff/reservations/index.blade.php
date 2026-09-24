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

    @if ($errors->any())
      <div class="alert alert-danger" data-testid="reservations-error-alert">
        <ul class="error-list">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Phone booking drawer -->
    <details class="kds-drawer phone-booking-drawer" data-testid="reservations-phone-booking-drawer">
      <summary class="btn btn-orange btn-sm" data-testid="reservations-phone-booking-summary">
        + New phone booking
      </summary>

      <form method="POST" action="{{ route('staff.reservations.store') }}" class="phone-booking-form" data-testid="reservations-phone-booking-form">
        @csrf
        <div class="phone-form-grid">
          <div class="form-group">
            <label for="phone-customer-id">Existing customer (optional)</label>
            <select id="phone-customer-id" name="customer_id" data-testid="phone-booking-customer-select">
              <option value="">— Guest booking (enter name &amp; phone below) —</option>
              @foreach ($recentCustomers as $c)
                <option value="{{ $c->customer_id }}">{{ $c->full_name }} ({{ $c->phone ?? $c->email }})</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label for="phone-guest-name">Guest name</label>
            <input type="text" id="phone-guest-name" name="guest_name" maxlength="100" placeholder="Full name" data-testid="phone-booking-guest-name">
          </div>

          <div class="form-group">
            <label for="phone-guest-phone">Guest mobile phone</label>
            <input type="text" id="phone-guest-phone" name="guest_phone" maxlength="20" placeholder="e.g. 0412345678" data-testid="phone-booking-guest-phone">
          </div>

          <div class="form-group">
            <label for="phone-booking-date">Booking date</label>
            <input type="date" id="phone-booking-date" name="booking_date" value="{{ $date }}" required data-testid="phone-booking-date">
          </div>

          <div class="form-group">
            <label for="phone-slot-id">Time slot</label>
            <select id="phone-slot-id" name="slot_id" required data-testid="phone-booking-slot-select">
              @foreach ($activeSlots as $slot)
                <option value="{{ $slot->slot_id }}">{{ substr($slot->slot_time, 0, 5) }} (max {{ $slot->max_covers }} covers)</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label for="phone-party-size">Party size (guests)</label>
            <input type="number" id="phone-party-size" name="party_size" min="1" max="50" value="2" required data-testid="phone-booking-party-size">
          </div>
        </div>

        <div class="form-group" style="margin-top:0.6rem">
          <label for="phone-special-requests">Special requests (optional)</label>
          <input type="text" id="phone-special-requests" name="special_requests" maxlength="500" placeholder="Dietary requirements, high chair, seating preference" data-testid="phone-booking-special-requests">
        </div>

        <div class="phone-form-actions" style="margin-top:0.8rem">
          <button type="submit" class="btn btn-orange" data-testid="phone-booking-submit-btn">
            Create confirmed booking
          </button>
        </div>
      </form>
    </details>

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

        <div class="reservation-card {{ $r->is_unassigned_inside_t30 ? 'card-unassigned-t30' : '' }} {{ $r->is_grace_elapsed ? 'card-grace-elapsed' : '' }}" data-testid="reservations-card-{{ $r->reservation_id }}">
          @if ($r->is_unassigned_inside_t30)
            <div class="unassigned-t30-banner" data-testid="reservations-unassigned-t30-alert-{{ $r->reservation_id }}">
              ⚠️ Unassigned Booking (T–30 min)
            </div>
          @endif

          @if ($r->is_grace_elapsed)
            <div class="no-show-alert-banner" data-testid="reservations-no-show-suggest-{{ $r->reservation_id }}">
              ⚠️ Grace period elapsed (suggest no-show)
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

          <!-- Table assignment info & controls -->
          <div class="card-table-section">
            <div class="card-table-info">
              <strong>Table:</strong>
              @if ($r->assigned_tables->isNotEmpty())
                <span class="assigned-table-list" data-testid="reservations-tables-{{ $r->reservation_id }}">
                  {{ $r->assigned_tables->pluck('table_number')->map(fn($n) => 'Table '.$n)->join(', ') }}
                </span>
                @if (in_array($r->status, [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::Requested], true))
                  <form method="POST" action="{{ route('staff.reservations.tables.unassign', $r) }}" style="display:inline; margin-left:0.4rem;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-xs btn-ghost text-danger" data-testid="reservations-unassign-btn-{{ $r->reservation_id }}">
                      Unassign
                    </button>
                  </form>
                @endif
              @else
                <span class="unassigned-tag {{ $r->is_unassigned_inside_t30 ? 'unassigned-urgent' : '' }}" data-testid="reservations-unassigned-tag-{{ $r->reservation_id }}">
                  Unassigned
                </span>
              @endif
            </div>

            @if (in_array($r->status, [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::Requested], true))
              <details class="table-assign-drawer" data-testid="reservations-assign-drawer-{{ $r->reservation_id }}">
                <summary class="btn btn-xs btn-outline" data-testid="reservations-toggle-assign-{{ $r->reservation_id }}">
                  {{ $r->assigned_tables->isNotEmpty() ? 'Reassign tables' : 'Assign tables' }}
                </summary>
                <form method="POST" action="{{ route('staff.reservations.tables.assign', $r) }}" class="table-assign-form" data-testid="reservations-assign-form-{{ $r->reservation_id }}">
                  @csrf
                  <p class="text-muted text-xs" style="margin-bottom:0.4rem">
                    Select active table(s) to seat {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('guest', $r->party_size) }}:
                  </p>
                  <div class="table-checkboxes-grid">
                    @php
                      $assignedIds = $r->assigned_tables->pluck('table_id')->all();
                    @endphp
                    @foreach ($allTables as $t)
                      <label class="table-chk-label">
                        <input type="checkbox" name="table_ids[]" value="{{ $t->table_id }}" {{ in_array($t->table_id, $assignedIds, true) ? 'checked' : '' }} data-testid="reservations-table-chk-{{ $r->reservation_id }}-{{ $t->table_id }}">
                        <span>T{{ $t->table_number }} ({{ $t->seat_capacity }}s)</span>
                      </label>
                    @endforeach
                  </div>
                  <button type="submit" class="btn btn-xs btn-primary" data-testid="reservations-save-tables-{{ $r->reservation_id }}">
                    Save assignment
                  </button>
                </form>
              </details>
            @endif
          </div>

          @if ($r->special_requests)
            <div class="card-requests" data-testid="reservations-requests-{{ $r->reservation_id }}">
              <span class="requests-label">Note:</span> &ldquo;{{ $r->special_requests }}&rdquo;
            </div>
          @endif

          <!-- Confirmed actions: Seat & Mark No-show -->
          @if ($r->status === \App\Enums\ReservationStatus::Confirmed)
            <div class="card-confirmed-actions" data-testid="reservations-confirmed-actions-{{ $r->reservation_id }}" style="margin-top:auto; padding-top:0.5rem; border-top:1px solid var(--line); display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
              @if ($r->can_seat)
                <form method="POST" action="{{ route('staff.reservations.seat', $r) }}" style="display:inline;">
                  @csrf
                  <button type="submit" class="btn btn-xs btn-primary" data-testid="reservations-seat-btn-{{ $r->reservation_id }}">
                    Seat guests
                  </button>
                </form>
              @endif

              <form method="POST" action="{{ route('staff.reservations.no-show', $r) }}" style="display:inline;" onsubmit="return confirm('Confirm mark {{ $r->reference_code }} as no-show? Any assigned tables will be released.');">
                @csrf
                <button type="submit" class="btn btn-xs btn-outline-danger" data-testid="reservations-no-show-btn-{{ $r->reservation_id }}">
                  Mark no-show
                </button>
              </form>
            </div>
          @endif

          <!-- Review panel for requested bookings -->
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
