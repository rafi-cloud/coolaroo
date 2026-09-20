@props([
    'availabilityService' => null,
    'settingService' => null,
])
@php
    $availability = $availabilityService ?? app(\App\Services\AvailabilityService::class);
    $settings = $settingService ?? app(\App\Services\SettingService::class);
    $onlineEnabled = $availability->isOnlineReservationsEnabled();
    $venue = $settings->venue();
    $venuePhone = $venue['phone'] ?? $settings->get('venue_phone', '03 9300 0000');
    $maxParty = $availability->getMaxPartyOnline();
    $maxDays = $availability->getMaxDaysAhead();
    $minLead = $availability->getMinLeadHours();
    $customer = auth('customer')->user();
@endphp

<section class="section reserve-section" id="reserve" data-testid="reservation-wizard-section">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule"></div>
      <h2>Book a table</h2>
      <p class="sub">Reserve your spot at Coolaroo for dining, celebrations and gatherings.</p>
    </div>

    @if (session('status') === 'reservation-requested')
      <div class="reserve-success-card" data-testid="reserve-success-banner">
        <div class="success-icon" aria-hidden="true">✓</div>
        <h3>Reservation request received!</h3>
        <p class="reserve-ref">Reference code: <strong data-testid="reserve-success-ref">{{ session('reservation_code') }}</strong></p>
        <p>{{ session('message') }}</p>
        <p class="muted text-sm" style="margin-top:0.6rem">
          We will review your request and send confirmation to your email.
        </p>
        <div style="margin-top:1.2rem; display:flex; gap:0.6rem; justify-content:center; flex-wrap:wrap;">
          <a href="{{ url('/#reserve') }}" class="btn btn-amber btn-sm" data-testid="reserve-new-booking-btn">Book another table</a>
          @auth('customer')
            <a href="{{ route('orders.show', 'dummy') ?? url('/orders') }}" class="btn btn-outline btn-sm" style="display:none">My Bookings</a>
          @endauth
        </div>
      </div>
    @elseif (!$onlineEnabled)
      <div class="reserve-paused-card" data-testid="reserve-paused-banner">
        <h3>Online reservations currently unavailable</h3>
        <p>Online bookings are paused at the moment. Please call us directly at <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venuePhone) }}" data-testid="reserve-phone-link"><strong>{{ $venuePhone }}</strong></a> to check table availability.</p>
      </div>
    @else
      <div class="wizard-container" data-testid="reserve-wizard-container">
        <!-- Step Indicators -->
        <div class="wizard-steps-indicator" aria-label="Booking steps">
          <div class="wizard-step-node active" id="step-node-1" data-testid="wizard-step-node-1">
            <span class="step-num">1</span>
            <span class="step-label">Party &amp; Date</span>
          </div>
          <div class="wizard-step-divider"></div>
          <div class="wizard-step-node" id="step-node-2" data-testid="wizard-step-node-2">
            <span class="step-num">2</span>
            <span class="step-label">Select Time</span>
          </div>
          <div class="wizard-step-divider"></div>
          <div class="wizard-step-node" id="step-node-3" data-testid="wizard-step-node-3">
            <span class="step-num">3</span>
            <span class="step-label">Guest Details</span>
          </div>
        </div>

        @if ($errors->any())
          <div class="alert alert-danger" style="margin-bottom:1.2rem;" data-testid="reserve-errors-banner">
            <ul style="margin:0; padding-left:1.2rem">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('reservations.store') }}" id="reservation-wizard-form" class="reserve-wizard-form" data-testid="reserve-wizard-form">
          @csrf
          <input type="hidden" name="slot_id" id="wizard-slot-id" value="{{ old('slot_id') }}" required>
          <input type="hidden" name="booking_date" id="wizard-booking-date-hidden" value="{{ old('booking_date', now()->toDateString()) }}" required>
          <input type="hidden" name="party_size" id="wizard-party-size-hidden" value="{{ old('party_size', 2) }}" required>

          <!-- Step 1: Party & Date -->
          <div class="wizard-step" id="wizard-step-1" data-testid="wizard-step-1">
            <div class="wizard-grid-2">
              <div class="form-group">
                <label for="wizard-party-size-select">Number of guests</label>
                <select id="wizard-party-size-select" class="wizard-input" data-testid="reserve-party-size-select">
                  @for ($i = 1; $i <= $maxParty; $i++)
                    <option value="{{ $i }}" {{ (old('party_size', 2) == $i) ? 'selected' : '' }}>
                      {{ $i }} {{ \Illuminate\Support\Str::plural('guest', $i) }}
                    </option>
                  @endfor
                </select>
                <span class="text-xs muted" style="margin-top:0.3rem; display:block">
                  For parties larger than {{ $maxParty }}, please call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venuePhone) }}">{{ $venuePhone }}</a>.
                </span>
              </div>

              <div class="form-group">
                <label for="wizard-date-input">Booking date</label>
                <input type="date" id="wizard-date-input" class="wizard-input" min="{{ now()->toDateString() }}" max="{{ now()->addDays($maxDays)->toDateString() }}" value="{{ old('booking_date', now()->toDateString()) }}" data-testid="reserve-date-input">
                <span class="text-xs muted" style="margin-top:0.3rem; display:block">
                  Venue closed Mondays. Minimum {{ $minLead }}h lead time.
                </span>
              </div>
            </div>

            <div class="wizard-actions" style="margin-top:1.5rem">
              <button type="button" id="wizard-step1-next-btn" class="btn btn-amber" data-testid="reserve-step1-next">
                Find available times &rarr;
              </button>
            </div>
          </div>

          <!-- Step 2: Select Time -->
          <div class="wizard-step" id="wizard-step-2" data-testid="wizard-step-2" style="display:none">
            <div class="wizard-chip" data-testid="reserve-step2-chip">
              <span id="chip-date-display">Date</span> &bull;
              <span id="chip-party-display">Guests</span> &bull;
              <span id="chip-duration-display">Table duration</span>
            </div>

            <div id="slots-loading" class="slots-loading-indicator" style="display:none" data-testid="reserve-slots-loading">
              Checking available tables...
            </div>

            <div id="no-slots-alert" class="alert alert-warning" style="display:none; margin:1rem 0;" data-testid="reserve-no-slots-alert">
              No online slots are available for the selected date and party size. Please pick another date or call us directly.
            </div>

            <div class="slots-picker-area">
              <label style="display:block; font-weight:700; margin-bottom:0.6rem; color:var(--ink);">
                Select an available time slot:
              </label>
              <div id="wizard-slots-grid" class="slots-grid" data-testid="reserve-slots-container">
                <!-- Injected dynamically via JS from /reservations/availability -->
              </div>
            </div>

            <div class="wizard-actions" style="margin-top:1.5rem">
              <button type="button" id="wizard-step2-back-btn" class="btn btn-outline" data-testid="reserve-step2-back">
                &larr; Change date / party
              </button>
              <button type="button" id="wizard-step2-next-btn" class="btn btn-amber" data-testid="reserve-step2-next" disabled>
                Continue to guest details &rarr;
              </button>
            </div>
          </div>

          <!-- Step 3: Guest Details & Notes -->
          <div class="wizard-step" id="wizard-step-3" data-testid="wizard-step-3" style="display:none">
            <div class="wizard-summary-box" data-testid="reserve-step3-summary">
              <strong>Selected booking:</strong>
              <span id="summary-booking-text">Party of 2 on 2026-09-25 at 18:00</span>
            </div>

            @if ($customer)
              <div class="customer-status-card" data-testid="reserve-customer-ready">
                <div class="customer-avatar-row">
                  <div>
                    <strong>{{ $customer->full_name }}</strong>
                    <div class="muted text-xs">{{ $customer->email }}</div>
                    @if ($customer->phone)
                      <div class="muted text-xs">Mobile: {{ $customer->phone }}</div>
                    @endif
                  </div>
                </div>

                @if (!$customer->hasVerifiedEmail())
                  <div class="alert alert-danger" style="margin-top:0.8rem" data-testid="reserve-unverified-alert">
                    Your email address ({{ $customer->email }}) is unverified. Please check your inbox for the verification link before booking.
                  </div>
                @endif

                @if (empty(trim((string) $customer->phone)))
                  <div class="alert alert-danger" style="margin-top:0.8rem" data-testid="reserve-no-phone-alert">
                    A mobile phone number is required on your account. Please <a href="{{ route('profile.edit') }}" style="text-decoration:underline">update your profile</a> with your phone number.
                  </div>
                @endif
              </div>
            @else
              <div class="visitor-login-prompt" data-testid="reserve-visitor-prompt">
                <p>Please log in or register to submit your reservation request:</p>
                <div class="prompt-buttons">
                  <a href="{{ route('customer.login') }}" class="btn btn-amber btn-sm" data-testid="reserve-login-btn">Log in</a>
                  <a href="{{ route('register') }}" class="btn btn-outline btn-sm" data-testid="reserve-register-btn">Create account</a>
                </div>
              </div>
            @endif

            <div class="form-group" style="margin-top:1.2rem">
              <label for="wizard-special-requests">Special requests (optional)</label>
              <textarea id="wizard-special-requests" name="special_requests" maxlength="500" class="wizard-textarea" placeholder="Dietary requirements, seating preference, high chair needed..." data-testid="reserve-notes-input"></textarea>
            </div>

            <div class="wizard-actions" style="margin-top:1.5rem">
              <button type="button" id="wizard-step3-back-btn" class="btn btn-outline" data-testid="reserve-step3-back">
                &larr; Change time
              </button>
              <button type="submit" id="wizard-submit-btn" class="btn btn-amber" data-testid="reserve-submit-btn" {{ (!$customer || !$customer->hasVerifiedEmail() || empty(trim((string) $customer->phone))) ? 'disabled' : '' }}>
                Request reservation
              </button>
            </div>
          </div>
        </form>
      </div>
    @endif
  </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var step1 = document.getElementById('wizard-step-1');
  var step2 = document.getElementById('wizard-step-2');
  var step3 = document.getElementById('wizard-step-3');

  var node1 = document.getElementById('step-node-1');
  var node2 = document.getElementById('step-node-2');
  var node3 = document.getElementById('step-node-3');

  var partySelect = document.getElementById('wizard-party-size-select');
  var dateInput = document.getElementById('wizard-date-input');

  var hiddenParty = document.getElementById('wizard-party-size-hidden');
  var hiddenDate = document.getElementById('wizard-booking-date-hidden');
  var hiddenSlot = document.getElementById('wizard-slot-id');

  var btnStep1Next = document.getElementById('wizard-step1-next-btn');
  var btnStep2Back = document.getElementById('wizard-step2-back-btn');
  var btnStep2Next = document.getElementById('wizard-step2-next-btn');
  var btnStep3Back = document.getElementById('wizard-step3-back-btn');

  var slotsGrid = document.getElementById('wizard-slots-grid');
  var slotsLoading = document.getElementById('slots-loading');
  var noSlotsAlert = document.getElementById('no-slots-alert');

  var chipDate = document.getElementById('chip-date-display');
  var chipParty = document.getElementById('chip-party-display');
  var chipDuration = document.getElementById('chip-duration-display');
  var summaryText = document.getElementById('summary-booking-text');

  var selectedTimeText = '';

  function getDuration(covers) {
    if (covers <= 2) return 90;
    if (covers <= 6) return 120;
    return 150;
  }

  function setStep(step) {
    if (!step1) return;
    step1.style.display = (step === 1) ? 'block' : 'none';
    step2.style.display = (step === 2) ? 'block' : 'none';
    step3.style.display = (step === 3) ? 'block' : 'none';

    node1.classList.toggle('active', step >= 1);
    node2.classList.toggle('active', step >= 2);
    node3.classList.toggle('active', step >= 3);
  }

  if (btnStep1Next) {
    btnStep1Next.addEventListener('click', function () {
      var party = parseInt(partySelect.value, 10) || 2;
      var date = dateInput.value;

      if (!date) {
        alert('Please select a booking date.');
        return;
      }

      hiddenParty.value = party;
      hiddenDate.value = date;

      var dur = getDuration(party);
      chipDate.textContent = date;
      chipParty.textContent = party + (party === 1 ? ' guest' : ' guests');
      chipDuration.textContent = dur + ' min table booking';

      // Load availability
      loadAvailability(date, party);
      setStep(2);
    });
  }

  if (btnStep2Back) {
    btnStep2Back.addEventListener('click', function () {
      setStep(1);
    });
  }

  if (btnStep2Next) {
    btnStep2Next.addEventListener('click', function () {
      if (!hiddenSlot.value) {
        alert('Please select a time slot.');
        return;
      }
      summaryText.textContent = chipParty.textContent + ' on ' + hiddenDate.value + ' at ' + selectedTimeText + ' (' + chipDuration.textContent + ')';
      setStep(3);
    });
  }

  if (btnStep3Back) {
    btnStep3Back.addEventListener('click', function () {
      setStep(2);
    });
  }

  function loadAvailability(date, party) {
    slotsGrid.innerHTML = '';
    slotsLoading.style.display = 'block';
    noSlotsAlert.style.display = 'none';
    btnStep2Next.disabled = true;
    hiddenSlot.value = '';

    fetch('/reservations/availability?date=' + encodeURIComponent(date) + '&party_size=' + encodeURIComponent(party), {
      headers: { 'Accept': 'application/json' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      slotsLoading.style.display = 'none';
      if (!data.available_slots || data.available_slots.length === 0) {
        noSlotsAlert.style.display = 'block';
        return;
      }

      data.available_slots.forEach(function (slot) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'slot-pill';
        btn.setAttribute('data-slot-id', slot.slot_id);
        btn.setAttribute('data-time', slot.slot_time);
        btn.setAttribute('data-testid', 'reserve-slot-pill-' + slot.slot_id);
        btn.innerHTML = '<strong>' + slot.slot_time.substring(0, 5) + '</strong>';

        btn.addEventListener('click', function () {
          var allPills = slotsGrid.querySelectorAll('.slot-pill');
          allPills.forEach(function (p) { p.classList.remove('selected'); });
          btn.classList.add('selected');
          hiddenSlot.value = slot.slot_id;
          selectedTimeText = slot.slot_time.substring(0, 5);
          btnStep2Next.disabled = false;
        });

        slotsGrid.appendChild(btn);
      });
    })
    .catch(function (err) {
      slotsLoading.style.display = 'none';
      noSlotsAlert.style.display = 'block';
      noSlotsAlert.textContent = 'Could not load slots. Please check your connection or contact venue.';
    });
  }
});
</script>
@endpush
