<x-layouts.admin title="Venue Settings" page-title="Venue Settings" page-sub="Manage restaurant contact details, trading hours, booking policies, and operational switches">
  <div class="card" data-testid="admin-settings-page">
    @if (session('status'))
      <div class="alert alert-success" role="status" style="margin-bottom:1.5rem;" data-testid="admin-settings-status">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger" role="alert" style="margin-bottom:1.5rem;" data-testid="admin-settings-errors">
        <strong style="display:block; margin-bottom:.4rem;">Please correct the errors below:</strong>
        <ul style="margin:0; padding-left:1.2rem;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

        <div style="margin-bottom:2rem; padding:1.5rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:10px;" data-testid="admin-settings-switches-card">
      <div style="margin-bottom:1.2rem;">
        <h2 style="font-size:1.15rem; color:var(--ink); margin:0 0 .25rem 0;">Real-Time Operational Switches</h2>
        <p style="font-size:.84rem; color:var(--cancelled); margin:0;">
          Instantly pause or resume public customer features without restarting or rebuilding the system. Changes take effect immediately via live WebSocket broadcasts.
        </p>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.2rem;">
                @php
          $qrEnabled = ($settings['qr_ordering_enabled'] ?? '1') === '1';
        @endphp
        <div style="background:var(--white); padding:1.2rem; border:1px solid {{ $qrEnabled ? 'var(--line)' : '#FCA5A5' }}; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;" data-testid="admin-switch-card-qr">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem;">
              <strong style="font-size:.95rem; color:var(--ink);">QR Ordering</strong>
              <span class="badge" style="background:{{ $qrEnabled ? '#D1FAE5' : '#FEE2E2' }}; color:{{ $qrEnabled ? '#065F46' : '#991B1B' }}; font-weight:700; font-size:.75rem; padding:.25rem .6rem; border-radius:100px;" data-testid="admin-switch-status-qr">
                {{ $qrEnabled ? 'ACTIVE' : 'PAUSED' }}
              </span>
            </div>
            <p style="font-size:.82rem; color:var(--body); line-height:1.45; margin:0 0 1rem 0;">
              When paused, seated diners can browse dishes and menu prices but cannot add lines to cart or checkout. Staff orders remain fully functional.
            </p>
          </div>
          <form method="POST" action="{{ route('admin.settings.toggle', 'qr_ordering_enabled') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm {{ $qrEnabled ? 'btn-outline' : 'btn-primary' }}" style="width:100%; justify-content:center;" data-testid="admin-switch-toggle-qr">
              {{ $qrEnabled ? 'Pause QR Ordering' : 'Enable QR Ordering' }}
            </button>
          </form>
        </div>

                @php
          $resEnabled = ($settings['reservations_online_enabled'] ?? '1') === '1';
        @endphp
        <div style="background:var(--white); padding:1.2rem; border:1px solid {{ $resEnabled ? 'var(--line)' : '#FCA5A5' }}; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;" data-testid="admin-switch-card-reservations">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem;">
              <strong style="font-size:.95rem; color:var(--ink);">Online Reservations</strong>
              <span class="badge" style="background:{{ $resEnabled ? '#D1FAE5' : '#FEE2E2' }}; color:{{ $resEnabled ? '#065F46' : '#991B1B' }}; font-weight:700; font-size:.75rem; padding:.25rem .6rem; border-radius:100px;" data-testid="admin-switch-status-reservations">
                {{ $resEnabled ? 'ACTIVE' : 'PAUSED' }}
              </span>
            </div>
            <p style="font-size:.82rem; color:var(--body); line-height:1.45; margin:0 0 1rem 0;">
              When paused, the public homepage reservation wizard disables online bookings and instructs guests to call the restaurant directly. Phone bookings remain enabled.
            </p>
          </div>
          <form method="POST" action="{{ route('admin.settings.toggle', 'reservations_online_enabled') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm {{ $resEnabled ? 'btn-outline' : 'btn-primary' }}" style="width:100%; justify-content:center;" data-testid="admin-switch-toggle-reservations">
              {{ $resEnabled ? 'Pause Online Bookings' : 'Enable Online Bookings' }}
            </button>
          </form>
        </div>

                @php
          $aiEnabled = ($settings['ai_enabled'] ?? '1') === '1';
        @endphp
        <div style="background:var(--white); padding:1.2rem; border:1px solid {{ $aiEnabled ? 'var(--line)' : '#FCA5A5' }}; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;" data-testid="admin-switch-card-ai">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem;">
              <strong style="font-size:.95rem; color:var(--ink);">AI Assistant</strong>
              <span class="badge" style="background:{{ $aiEnabled ? '#D1FAE5' : '#FEE2E2' }}; color:{{ $aiEnabled ? '#065F46' : '#991B1B' }}; font-weight:700; font-size:.75rem; padding:.25rem .6rem; border-radius:100px;" data-testid="admin-switch-status-ai">
                {{ $aiEnabled ? 'ACTIVE' : 'PAUSED' }}
              </span>
            </div>
            <p style="font-size:.82rem; color:var(--body); line-height:1.45; margin:0 0 1rem 0;">
              When paused, hides the AI chat widget and meal builder across the website. Existing dining cart lines are retained.
            </p>
          </div>
          <form method="POST" action="{{ route('admin.settings.toggle', 'ai_enabled') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm {{ $aiEnabled ? 'btn-outline' : 'btn-primary' }}" style="width:100%; justify-content:center;" data-testid="admin-switch-toggle-ai">
              {{ $aiEnabled ? 'Pause AI Assistant' : 'Enable AI Assistant' }}
            </button>
          </form>
        </div>
      </div>
    </div>

        <form method="POST" action="{{ route('admin.settings.update') }}" data-testid="admin-settings-form">
      @csrf
      @method('PATCH')

            <input type="hidden" name="qr_ordering_enabled" value="{{ $qrEnabled ? '1' : '0' }}">
      <input type="hidden" name="reservations_online_enabled" value="{{ $resEnabled ? '1' : '0' }}">
      <input type="hidden" name="ai_enabled" value="{{ $aiEnabled ? '1' : '0' }}">

            <div style="margin-bottom:2.2rem; padding-bottom:1.8rem; border-bottom:1px solid var(--line);">
        <h2 style="font-size:1.15rem; color:var(--ink); margin:0 0 .3rem 0;">1. Venue Identity &amp; Contact Details</h2>
        <p style="font-size:.84rem; color:var(--cancelled); margin:0 0 1.2rem 0;">
          Displayed in website headers, footers, customer confirmation emails, and provided in AI context.
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1.2rem;">
          <div class="field">
            <label for="venue_name" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Venue Name <span style="color:var(--danger)">*</span></label>
            <input type="text" id="venue_name" name="venue_name" required maxlength="100" value="{{ old('venue_name', $settings['venue_name'] ?? '') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-venue-name">
          </div>

          <div class="field">
            <label for="venue_phone" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Telephone <span style="color:var(--danger)">*</span></label>
            <input type="text" id="venue_phone" name="venue_phone" required maxlength="30" value="{{ old('venue_phone', $settings['venue_phone'] ?? '') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-venue-phone">
          </div>

          <div class="field">
            <label for="venue_email" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Email Address <span style="color:var(--danger)">*</span></label>
            <input type="email" id="venue_email" name="venue_email" required maxlength="100" value="{{ old('venue_email', $settings['venue_email'] ?? '') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-venue-email">
          </div>
        </div>

        <div class="field" style="margin-top:1.2rem;">
          <label for="venue_address" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Street Address <span style="color:var(--danger)">*</span></label>
          <input type="text" id="venue_address" name="venue_address" required maxlength="255" value="{{ old('venue_address', $settings['venue_address'] ?? '') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-venue-address">
        </div>

        <h3 style="font-size:.95rem; color:var(--ink); margin:1.4rem 0 .6rem 0;">Social Media Links</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
          <div class="field">
            <label for="social_facebook" style="font-size:.8rem; color:var(--cancelled); display:block; margin-bottom:.25rem;">Facebook URL</label>
            <input type="text" id="social_facebook" name="social_facebook" maxlength="255" value="{{ old('social_facebook', $settings['social_facebook'] ?? '') }}" placeholder="https://facebook.com/..." style="width:100%; padding:.45rem .65rem; font-size:.82rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-social-facebook">
          </div>
          <div class="field">
            <label for="social_instagram" style="font-size:.8rem; color:var(--cancelled); display:block; margin-bottom:.25rem;">Instagram URL</label>
            <input type="text" id="social_instagram" name="social_instagram" maxlength="255" value="{{ old('social_instagram', $settings['social_instagram'] ?? '') }}" placeholder="https://instagram.com/..." style="width:100%; padding:.45rem .65rem; font-size:.82rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-social-instagram">
          </div>
          <div class="field">
            <label for="social_x" style="font-size:.8rem; color:var(--cancelled); display:block; margin-bottom:.25rem;">X / Twitter URL</label>
            <input type="text" id="social_x" name="social_x" maxlength="255" value="{{ old('social_x', $settings['social_x'] ?? '') }}" placeholder="https://x.com/..." style="width:100%; padding:.45rem .65rem; font-size:.82rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-social-x">
          </div>
          <div class="field">
            <label for="social_tiktok" style="font-size:.8rem; color:var(--cancelled); display:block; margin-bottom:.25rem;">TikTok URL</label>
            <input type="text" id="social_tiktok" name="social_tiktok" maxlength="255" value="{{ old('social_tiktok', $settings['social_tiktok'] ?? '') }}" placeholder="https://tiktok.com/@..." style="width:100%; padding:.45rem .65rem; font-size:.82rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-social-tiktok">
          </div>
          <div class="field">
            <label for="social_whatsapp" style="font-size:.8rem; color:var(--cancelled); display:block; margin-bottom:.25rem;">WhatsApp URL</label>
            <input type="text" id="social_whatsapp" name="social_whatsapp" maxlength="255" value="{{ old('social_whatsapp', $settings['social_whatsapp'] ?? '') }}" placeholder="https://wa.me/..." style="width:100%; padding:.45rem .65rem; font-size:.82rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-social-whatsapp">
          </div>
        </div>
      </div>

            <div style="margin-bottom:2.2rem; padding-bottom:1.8rem; border-bottom:1px solid var(--line);">
        <h2 style="font-size:1.15rem; color:var(--ink); margin:0 0 .3rem 0;">2. Operating Hours &amp; Closed Days</h2>
        <p style="font-size:.84rem; color:var(--cancelled); margin:0 0 1.2rem 0;">
          Defines daily schedule, automated daily stock resets, unpaid order expiration at close, and closed weekdays for booking availability.
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.2rem; margin-bottom:1.2rem;">
          <div class="field">
            <label for="opening_time" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Daily Opening Time <span style="color:var(--danger)">*</span></label>
            <input type="time" id="opening_time" name="opening_time" required value="{{ old('opening_time', $settings['opening_time'] ?? '11:00') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-opening-time">
          </div>
          <div class="field">
            <label for="closing_time" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Daily Closing Time <span style="color:var(--danger)">*</span></label>
            <input type="time" id="closing_time" name="closing_time" required value="{{ old('closing_time', $settings['closing_time'] ?? '23:00') }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-closing-time">
          </div>
        </div>

        <div class="field">
          <label style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.5rem;">Closed Weekdays (Check days restaurant is closed to dining)</label>
          <div style="display:flex; flex-wrap:wrap; gap:1.2rem;">
            @php
              $dayLabels = [
                  1 => 'Monday',
                  2 => 'Tuesday',
                  3 => 'Wednesday',
                  4 => 'Thursday',
                  5 => 'Friday',
                  6 => 'Saturday',
                  7 => 'Sunday',
              ];
              $activeClosed = old('closed_weekdays', $closedWeekdays);
            @endphp
            @foreach ($dayLabels as $dNum => $dName)
              <label style="display:flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--ink); cursor:pointer;">
                <input type="checkbox" name="closed_weekdays[]" value="{{ $dNum }}" @checked(in_array((string) $dNum, $activeClosed, true)) data-testid="admin-setting-closed-day-{{ $dNum }}">
                <span>{{ $dName }}</span>
              </label>
            @endforeach
          </div>
        </div>
      </div>

            <div style="margin-bottom:2.2rem; padding-bottom:1.8rem; border-bottom:1px solid var(--line);">
        <h2 style="font-size:1.15rem; color:var(--ink); margin:0 0 .3rem 0;">3. Reservation Rules &amp; Dining Durations</h2>
        <p style="font-size:.84rem; color:var(--cancelled); margin:0 0 1.2rem 0;">
          Governs booking wizard lead times, party size restrictions, turnover durations, customer edit locks, and arrival grace periods.
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.2rem;">
          <div class="field">
            <label for="reservation_max_days_ahead" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Max Days Ahead</label>
            <input type="number" id="reservation_max_days_ahead" name="reservation_max_days_ahead" required min="1" max="365" value="{{ old('reservation_max_days_ahead', $settings['reservation_max_days_ahead'] ?? 60) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-max-days-ahead">
            <span style="font-size:.72rem; color:var(--cancelled);">Days into the future guests can reserve</span>
          </div>

          <div class="field">
            <label for="reservation_min_lead_hours" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Min Lead Hours</label>
            <input type="number" id="reservation_min_lead_hours" name="reservation_min_lead_hours" required min="0" max="72" value="{{ old('reservation_min_lead_hours', $settings['reservation_min_lead_hours'] ?? 2) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-min-lead-hours">
            <span style="font-size:.72rem; color:var(--cancelled);">Minimum notice required before booking time</span>
          </div>

          <div class="field">
            <label for="reservation_max_party_online" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Max Online Party Size</label>
            <input type="number" id="reservation_max_party_online" name="reservation_max_party_online" required min="1" max="50" value="{{ old('reservation_max_party_online', $settings['reservation_max_party_online'] ?? 10) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-max-party-online">
            <span style="font-size:.72rem; color:var(--cancelled);">Covers above this limit must book via phone</span>
          </div>
        </div>

        <h3 style="font-size:.95rem; color:var(--ink); margin:1.4rem 0 .6rem 0;">Turnover Durations by Party Size</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.2rem;">
          <div class="field">
            <label for="reservation_duration_1_2" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">1–2 Covers (Minutes)</label>
            <input type="number" id="reservation_duration_1_2" name="reservation_duration_1_2" required min="15" max="300" value="{{ old('reservation_duration_1_2', $settings['reservation_duration_1_2'] ?? 90) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-duration-1-2">
          </div>
          <div class="field">
            <label for="reservation_duration_3_6" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">3–6 Covers (Minutes)</label>
            <input type="number" id="reservation_duration_3_6" name="reservation_duration_3_6" required min="15" max="300" value="{{ old('reservation_duration_3_6', $settings['reservation_duration_3_6'] ?? 120) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-duration-3-6">
          </div>
          <div class="field">
            <label for="reservation_duration_7_plus" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">7+ Covers (Minutes)</label>
            <input type="number" id="reservation_duration_7_plus" name="reservation_duration_7_plus" required min="15" max="300" value="{{ old('reservation_duration_7_plus', $settings['reservation_duration_7_plus'] ?? 150) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-duration-7-plus">
          </div>
        </div>

        <h3 style="font-size:.95rem; color:var(--ink); margin:1.4rem 0 .6rem 0;">Booking Lifecycle Timers &amp; Grace Windows</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.2rem;">
          <div class="field">
            <label for="reservation_request_expiry_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Request Expiry</label>
            <input type="number" id="reservation_request_expiry_minutes" name="reservation_request_expiry_minutes" required min="10" max="720" value="{{ old('reservation_request_expiry_minutes', $settings['reservation_request_expiry_minutes'] ?? 60) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-request-expiry">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes before slot unreviewed requests expire</span>
          </div>

          <div class="field">
            <label for="reservation_reminder_hours" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Reminder Email</label>
            <input type="number" id="reservation_reminder_hours" name="reservation_reminder_hours" required min="1" max="168" value="{{ old('reservation_reminder_hours', $settings['reservation_reminder_hours'] ?? 24) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-reminder-hours">
            <span style="font-size:.72rem; color:var(--cancelled);">Hours prior to dispatch automated reminder</span>
          </div>

          <div class="field">
            <label for="late_cancellation_hours" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Cancellation / Edit Lock</label>
            <input type="number" id="late_cancellation_hours" name="late_cancellation_hours" required min="0" max="48" value="{{ old('late_cancellation_hours', $settings['late_cancellation_hours'] ?? 2) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-late-cancel-hours">
            <span style="font-size:.72rem; color:var(--cancelled);">Hours before booking edits are locked</span>
          </div>

          <div class="field">
            <label for="holder_unlock_before_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Customer Scan Grace</label>
            <input type="number" id="holder_unlock_before_minutes" name="holder_unlock_before_minutes" required min="0" max="120" value="{{ old('holder_unlock_before_minutes', $settings['holder_unlock_before_minutes'] ?? 15) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-holder-unlock">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes early customer QR scan seats table</span>
          </div>

          <div class="field">
            <label for="reservation_grace_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Arrival Grace Period</label>
            <input type="number" id="reservation_grace_minutes" name="reservation_grace_minutes" required min="1" max="120" value="{{ old('reservation_grace_minutes', $settings['reservation_grace_minutes'] ?? 15) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-grace-minutes">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes after slot before no-show suggested</span>
          </div>

          <div class="field">
            <label for="reserved_switch_before_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Table Sign Switch</label>
            <input type="number" id="reserved_switch_before_minutes" name="reserved_switch_before_minutes" required min="1" max="180" value="{{ old('reserved_switch_before_minutes', $settings['reserved_switch_before_minutes'] ?? 30) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-reserved-switch">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes before slot table marked Reserved</span>
          </div>

          <div class="field">
            <label for="unassigned_admin_alert_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Unassigned Alert</label>
            <input type="number" id="unassigned_admin_alert_minutes" name="unassigned_admin_alert_minutes" required min="1" max="180" value="{{ old('unassigned_admin_alert_minutes', $settings['unassigned_admin_alert_minutes'] ?? 15) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-unassigned-alert">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes before slot unassigned booking alerts floor</span>
          </div>
        </div>
      </div>

            <div style="margin-bottom:2.2rem; padding-bottom:1.8rem; border-bottom:1px solid var(--line);">
        <h2 style="font-size:1.15rem; color:var(--ink); margin:0 0 .3rem 0;">4. Service, Stock &amp; Operational Timers</h2>
        <p style="font-size:.84rem; color:var(--cancelled); margin:0 0 1.2rem 0;">
          Buffers for live QR ordering, kitchen/bar ETA calculations, waiter calling cooldowns, and trust badges.
        </p>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:1.2rem;">
          <div class="field">
            <label for="qr_stock_buffer_multiplier" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Stock Buffer Multiplier</label>
            <input type="number" id="qr_stock_buffer_multiplier" name="qr_stock_buffer_multiplier" required min="1" max="50" value="{{ old('qr_stock_buffer_multiplier', $settings['qr_stock_buffer_multiplier'] ?? 5) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-stock-buffer">
            <span style="font-size:.72rem; color:var(--cancelled);">Safety stock buffer multiplier for QR checkout</span>
          </div>

          <div class="field">
            <label for="table_idle_autoclear_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Table Idle Auto-Clear</label>
            <input type="number" id="table_idle_autoclear_minutes" name="table_idle_autoclear_minutes" required min="5" max="180" value="{{ old('table_idle_autoclear_minutes', $settings['table_idle_autoclear_minutes'] ?? 45) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-idle-autoclear">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes of inactivity before table reverts Available</span>
          </div>

          <div class="field">
            <label for="avg_ticket_minutes_kitchen" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Kitchen Prep Baseline</label>
            <input type="number" id="avg_ticket_minutes_kitchen" name="avg_ticket_minutes_kitchen" required min="1" max="60" value="{{ old('avg_ticket_minutes_kitchen', $settings['avg_ticket_minutes_kitchen'] ?? 8) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-kitchen-eta">
            <span style="font-size:.72rem; color:var(--cancelled);">Base minutes per kitchen line for ETA calculation</span>
          </div>

          <div class="field">
            <label for="avg_ticket_minutes_bar" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Bar Prep Baseline</label>
            <input type="number" id="avg_ticket_minutes_bar" name="avg_ticket_minutes_bar" required min="1" max="60" value="{{ old('avg_ticket_minutes_bar', $settings['avg_ticket_minutes_bar'] ?? 3) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-bar-eta">
            <span style="font-size:.72rem; color:var(--cancelled);">Base minutes per beverage ticket for ETA calculation</span>
          </div>

          <div class="field">
            <label for="call_waiter_cooldown_seconds" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Call Waiter Cooldown</label>
            <input type="number" id="call_waiter_cooldown_seconds" name="call_waiter_cooldown_seconds" required min="10" max="600" value="{{ old('call_waiter_cooldown_seconds', $settings['call_waiter_cooldown_seconds'] ?? 120) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-call-waiter-cooldown">
            <span style="font-size:.72rem; color:var(--cancelled);">Seconds between waiter call button presses per table</span>
          </div>

          <div class="field">
            <label for="staff_session_timeout_minutes" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Staff Inactivity Logout</label>
            <input type="number" id="staff_session_timeout_minutes" name="staff_session_timeout_minutes" required min="5" max="480" value="{{ old('staff_session_timeout_minutes', $settings['staff_session_timeout_minutes'] ?? 30) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-session-timeout">
            <span style="font-size:.72rem; color:var(--cancelled);">Minutes of idle staff session before re-authentication</span>
          </div>

          <div class="field">
            <label for="regular_badge_visits" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Regular Trust Visits</label>
            <input type="number" id="regular_badge_visits" name="regular_badge_visits" required min="1" max="50" value="{{ old('regular_badge_visits', $settings['regular_badge_visits'] ?? 3) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-regular-visits">
            <span style="font-size:.72rem; color:var(--cancelled);">Completed visits required for Regular trust badge</span>
          </div>

          <div class="field">
            <label for="no_show_expiry_months" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">No-Show Trust Window</label>
            <input type="number" id="no_show_expiry_months" name="no_show_expiry_months" required min="1" max="60" value="{{ old('no_show_expiry_months', $settings['no_show_expiry_months'] ?? 12) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-no-show-window">
            <span style="font-size:.72rem; color:var(--cancelled);">Months a no-show affects customer trust standing</span>
          </div>

          <div class="field">
            <label for="public_rating_min_count" style="font-size:.82rem; font-weight:600; display:block; margin-bottom:.35rem;">Rating Card Min Reviews</label>
            <input type="number" id="public_rating_min_count" name="public_rating_min_count" required min="1" max="100" value="{{ old('public_rating_min_count', $settings['public_rating_min_count'] ?? 10) }}" style="width:100%; padding:.5rem .75rem; font-size:.88rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-setting-rating-min-count">
            <span style="font-size:.72rem; color:var(--cancelled);">Minimum verified reviews to display public score</span>
          </div>
        </div>
      </div>

            <div style="display:flex; justify-content:flex-end; gap:.8rem; align-items:center;">
        <button type="submit" class="btn btn-primary" style="padding:.65rem 1.8rem; font-size:.95rem;" data-testid="admin-settings-save-button">
          Save All Settings
        </button>
      </div>
    </form>
  </div>
</x-layouts.admin>
