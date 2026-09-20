@props(['reservation'])

@php
    $profile = $reservation->trust_profile ?? app(\App\Services\TrustService::class)->profile($reservation->customer);
    $badge = $profile['badge'] ?? 'New';
    $badgeClass = match ($badge) {
        'Flagged' => 'badge-flagged',
        'Regular' => 'badge-regular',
        default => 'badge-new',
    };
@endphp

<details class="reservation-review-panel" data-testid="reservations-review-panel-{{ $reservation->reservation_id }}">
  <summary class="btn btn-outline btn-sm" data-testid="reservations-review-btn-{{ $reservation->reservation_id }}">
    Review request
  </summary>

  <div class="review-panel-content">
    <div class="trust-profile-card" data-testid="trust-profile-{{ $reservation->reservation_id }}">
      <div class="trust-header">
        <h4>Customer trust profile</h4>
        <span class="trust-badge {{ $badgeClass }}" data-testid="trust-badge-{{ $reservation->reservation_id }}">
          {{ $badge }}
        </span>
      </div>

      <div class="trust-stats-grid">
        <div class="stat-box">
          <span class="stat-num" data-testid="trust-completed-{{ $reservation->reservation_id }}">{{ $profile['completed_visits_count'] }}</span>
          <span class="stat-lbl">Visits</span>
        </div>
        <div class="stat-box">
          <span class="stat-num" data-testid="trust-cancelled-{{ $reservation->reservation_id }}">{{ $profile['cancellations_count'] }}</span>
          <span class="stat-lbl">Cancelled</span>
        </div>
        <div class="stat-box {{ $profile['uncleared_no_shows_count'] > 0 ? 'stat-box-danger' : '' }}">
          <span class="stat-num" data-testid="trust-noshows-{{ $reservation->reservation_id }}">{{ $profile['uncleared_no_shows_count'] }}</span>
          <span class="stat-lbl">No-shows</span>
        </div>
      </div>

      @if ($profile['uncleared_no_shows_count'] > 0)
        <p class="trust-warning" data-testid="trust-warning-{{ $reservation->reservation_id }}">
          ⚠️ Customer has {{ $profile['uncleared_no_shows_count'] }} uncleared no-show in the last 12 months.
        </p>
      @endif
    </div>

    <div class="review-booking-summary">
      <p><strong>Party:</strong> {{ $reservation->party_size }} guests &middot; <strong>Time:</strong> {{ substr($reservation->booking_time, 0, 5) }}</p>
      @if ($reservation->special_requests)
        <p class="review-special-requests">
          <strong>Special requests:</strong> &ldquo;{{ $reservation->special_requests }}&rdquo;
        </p>
      @endif
    </div>

    <div class="review-actions">
      <form method="POST" action="{{ route('staff.reservations.approve', $reservation) }}" class="inline-form">
        @csrf
        <button type="submit" class="btn btn-orange" data-testid="reservations-approve-btn-{{ $reservation->reservation_id }}">
          Approve booking
        </button>
      </form>

      <form method="POST" action="{{ route('staff.reservations.decline', $reservation) }}" class="inline-form decline-form">
        @csrf
        <div class="auth-field">
          <label for="decline-reason-{{ $reservation->reservation_id }}">Decline reason (optional)</label>
          <input type="text" id="decline-reason-{{ $reservation->reservation_id }}" name="decline_reason" maxlength="255" placeholder="e.g. Fully booked" data-testid="reservations-decline-reason-{{ $reservation->reservation_id }}">
        </div>
        <button type="submit" class="btn btn-danger" data-testid="reservations-decline-btn-{{ $reservation->reservation_id }}">
          Decline
        </button>
      </form>
    </div>
  </div>
</details>
