<x-layouts.customer title="My reservations">
  <div class="wrap customer-container" data-testid="reservations-page">
    <x-customer.nav-tabs active="reservations" />

    <div class="customer-page-header">
      <div>
        <h1>My reservations</h1>
        <p class="sub">Manage your dining reservations and view past bookings</p>
      </div>
      <div>
        <a href="{{ url('/#reserve') }}" class="btn btn-orange btn-sm" data-testid="new-reservation-link">+ Book a table</a>
      </div>
    </div>

    @if (session('message'))
      <div class="alert alert-success" style="margin-bottom:1.5rem" data-testid="reservation-alert-success">
        {{ session('message') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger" style="margin-bottom:1.5rem" data-testid="reservation-alert-error">
        <ul style="list-style:disc;padding-left:1.2rem;margin:0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Upcoming Bookings -->
    <section class="customer-section" data-testid="upcoming-reservations-section">
      <h2 style="font-size:1.25rem;margin-bottom:1rem">Upcoming bookings ({{ $upcoming->count() }})</h2>

      @if ($upcoming->isEmpty())
        <div class="customer-empty-card" data-testid="no-upcoming-reservations">
          <div class="empty-icon" aria-hidden="true">📅</div>
          <h3>No upcoming reservations</h3>
          <p>You do not currently have any active or pending table bookings.</p>
          <div style="margin-top:1rem">
            <a href="{{ url('/#reserve') }}" class="btn btn-amber btn-sm" data-testid="book-table-empty-btn">Book a table</a>
          </div>
        </div>
      @else
        <div class="reservations-grid">
          @foreach ($upcoming as $res)
            @php
              $assignedTables = $res->visits->map->restaurantTable->filter()->pluck('table_number')->unique();
            @endphp
            <div class="res-card" data-testid="reservation-card-{{ $res->reservation_id }}">
              <div class="res-card-head">
                <div class="res-ref-group">
                  <span class="res-ref-code" data-testid="res-ref-{{ $res->reservation_id }}">{{ $res->reference_code }}</span>
                  <span class="res-date-sub">{{ $res->booking_date->format('D, j M Y') }}</span>
                </div>
                <div>
                  @if ($res->status->value === 'requested')
                    <span class="badge badge-amber" data-testid="res-status-{{ $res->reservation_id }}">Requested (Pending review)</span>
                  @elseif ($res->status->value === 'confirmed')
                    <span class="badge badge-green" data-testid="res-status-{{ $res->reservation_id }}">Confirmed</span>
                  @else
                    <span class="badge" data-testid="res-status-{{ $res->reservation_id }}">{{ ucfirst($res->status->value) }}</span>
                  @endif
                </div>
              </div>

              <div class="res-card-details">
                <div class="res-detail-row">
                  <span class="res-label">Time:</span>
                  <strong>{{ substr($res->booking_time, 0, 5) }}</strong>
                </div>
                <div class="res-detail-row">
                  <span class="res-label">Guests:</span>
                  <span>{{ $res->party_size }} {{ Str::plural('guest', $res->party_size) }}</span>
                </div>
                @if ($assignedTables->isNotEmpty())
                  <div class="res-detail-row">
                    <span class="res-label">Table:</span>
                    <span class="table-chip">Table {{ $assignedTables->implode(', ') }}</span>
                  </div>
                @endif
                @if ($res->special_requests)
                  <div class="res-detail-row" style="align-items:flex-start">
                    <span class="res-label">Requests:</span>
                    <span class="res-requests-text">{{ $res->special_requests }}</span>
                  </div>
                @endif
              </div>

              <!-- Action Drawers -->
              <div class="res-card-actions">
                <!-- Edit Disclosure -->
                <details class="res-details-panel" data-testid="edit-details-{{ $res->reservation_id }}">
                  <summary class="btn btn-outline btn-sm" data-testid="edit-res-btn-{{ $res->reservation_id }}">
                    Edit booking
                  </summary>
                  <div class="res-drawer-content">
                    <form method="POST" action="{{ route('reservations.update', $res) }}">
                      @csrf
                      @method('PATCH')
                      <div class="res-drawer-notice">
                        <strong>Important:</strong> Changes to date, time or party size require staff review and revert status to <em>Requested</em>. Edits are locked within 2 hours of booking time.
                      </div>
                      
                      <div class="form-row-2">
                        <div class="form-group">
                          <label for="edit-date-{{ $res->reservation_id }}">Date</label>
                          <input type="date" id="edit-date-{{ $res->reservation_id }}" name="booking_date" value="{{ old('booking_date', $res->booking_date->toDateString()) }}" class="form-input" min="{{ date('Y-m-d') }}" data-testid="edit-date-input-{{ $res->reservation_id }}">
                        </div>
                        <div class="form-group">
                          <label for="edit-slot-{{ $res->reservation_id }}">Time slot</label>
                          <select id="edit-slot-{{ $res->reservation_id }}" name="slot_id" class="form-input" data-testid="edit-slot-select-{{ $res->reservation_id }}">
                            @foreach ($activeSlots as $slot)
                              <option value="{{ $slot->slot_id }}" {{ $slot->slot_id === $res->slot_id ? 'selected' : '' }}>
                                {{ substr($slot->slot_time, 0, 5) }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                      </div>

                      <div class="form-group" style="margin-top:0.75rem">
                        <label for="edit-party-{{ $res->reservation_id }}">Party size</label>
                        <select id="edit-party-{{ $res->reservation_id }}" name="party_size" class="form-input" data-testid="edit-party-select-{{ $res->reservation_id }}">
                          @for ($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ $res->party_size === $i ? 'selected' : '' }}>
                              {{ $i }} {{ $i === 1 ? 'guest' : 'guests' }}
                            </option>
                          @endfor
                        </select>
                      </div>

                      <div class="form-group" style="margin-top:0.75rem">
                        <label for="edit-requests-{{ $res->reservation_id }}">Special requests (optional)</label>
                        <textarea id="edit-requests-{{ $res->reservation_id }}" name="special_requests" maxlength="500" class="form-input" rows="2" placeholder="Dietary requirements, seating preferences, etc." data-testid="edit-requests-input-{{ $res->reservation_id }}">{{ old('special_requests', $res->special_requests) }}</textarea>
                      </div>

                      <div class="res-drawer-actions">
                        <button type="submit" class="btn btn-amber btn-sm" data-testid="save-res-btn-{{ $res->reservation_id }}">Save changes</button>
                      </div>
                    </form>
                  </div>
                </details>

                <!-- Cancel Disclosure -->
                <details class="res-details-panel" data-testid="cancel-details-{{ $res->reservation_id }}">
                  <summary class="btn btn-outline btn-sm btn-danger-outline" data-testid="cancel-res-btn-{{ $res->reservation_id }}">
                    Cancel booking
                  </summary>
                  <div class="res-drawer-content res-cancel-box">
                    <p style="font-weight:600;margin-bottom:0.3rem">Cancel reservation {{ $res->reference_code }}?</p>
                    <p style="font-size:0.8rem;color:#9B1C1C;margin-bottom:0.8rem;line-height:1.4">
                      Cancellations made within 2 hours of the booking time are recorded as late cancellations.
                    </p>
                    <form method="POST" action="{{ route('reservations.cancel', $res) }}">
                      @csrf
                      <button type="submit" class="btn btn-danger btn-sm" data-testid="confirm-cancel-res-btn-{{ $res->reservation_id }}">
                        Confirm cancellation
                      </button>
                    </form>
                  </div>
                </details>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </section>

    <!-- Past Bookings -->
    <section class="customer-section" style="margin-top:3rem" data-testid="past-reservations-section">
      <h2 style="font-size:1.25rem;margin-bottom:1rem">Past reservations</h2>

      @if ($past->isEmpty())
        <p class="muted">No past reservations recorded.</p>
      @else
        <div class="table-responsive">
          <table class="data-table" data-testid="past-reservations-table">
            <thead>
              <tr>
                <th>Reference</th>
                <th>Date</th>
                <th>Time</th>
                <th>Guests</th>
                <th>Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($past as $item)
                <tr data-testid="past-res-row-{{ $item->reservation_id }}">
                  <td><strong>{{ $item->reference_code }}</strong></td>
                  <td>{{ $item->booking_date->format('d/m/Y') }}</td>
                  <td>{{ substr($item->booking_time, 0, 5) }}</td>
                  <td>{{ $item->party_size }}</td>
                  <td>
                    @if ($item->status->value === 'seated')
                      <span class="badge badge-green">Seated</span>
                    @elseif ($item->status->value === 'completed')
                      <span class="badge badge-gray">Completed</span>
                    @elseif ($item->status->value === 'cancelled')
                      <span class="badge badge-gray">Cancelled</span>
                      @if ($item->is_late_cancellation)
                        <span class="badge badge-red" style="margin-left:0.3rem">Late</span>
                      @endif
                    @elseif ($item->status->value === 'declined')
                      <span class="badge badge-red">Declined</span>
                    @elseif ($item->status->value === 'no_show')
                      <span class="badge badge-red">No show</span>
                    @else
                      <span class="badge badge-gray">{{ ucfirst($item->status->value) }}</span>
                    @endif
                  </td>
                  <td class="text-sm muted">
                    @if ($item->status->value === 'declined' && $item->decline_reason)
                      <em>Reason: {{ $item->decline_reason }}</em>
                    @elseif ($item->special_requests)
                      {{ Str::limit($item->special_requests, 40) }}
                    @else
                      &mdash;
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="customer-pagination" data-testid="past-reservations-pagination">
          {{ $past->links() }}
        </div>
      @endif
    </section>
  </div>
</x-layouts.customer>
