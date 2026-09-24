@props(['table', 'tables', 'assignable'])
@php
  $seatable = $table->seatableReservations();
  $held = $table->heldReservations();
  $heldIds = $held->pluck('reservation_id')->all();
  $offered = $assignable->reject(fn ($reservation) => in_array($reservation->reservation_id, $heldIds, true));
@endphp
<details class="kds-drawer floor-table-drawer" data-testid="floor-table-drawer-{{ $table->table_id }}">
  {{-- the drawer holds the actions this table's status allows --}}
  <summary>
    @if ($table->status === \App\Enums\TableStatus::Occupied)
      Clear table
    @else
      Seat guests
    @endif
  </summary>

  @if ($table->status !== \App\Enums\TableStatus::Occupied)
    @if ($seatable->isNotEmpty())
      <form method="POST" action="{{ route('staff.tables.seat-reservation', $table) }}" class="floor-drawer-form">
        @csrf
        <label for="reservation-{{ $table->table_id }}">Seat booking</label>
        <select id="reservation-{{ $table->table_id }}" name="reservation_id" data-testid="floor-seat-reservation-select-{{ $table->table_id }}">
          @foreach ($seatable as $reservation)
            <option value="{{ $reservation->reservation_id }}">
              {{ $reservation->slot?->slot_time }} &middot; {{ $reservation->reference_code }} &middot; {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('guest', $reservation->party_size) }}
              @php($otherTables = $reservation->visits->whereNull('closed_at')->pluck('table_id')->reject(fn ($id) => $id === $table->table_id))
              @if ($otherTables->isNotEmpty())
                (also seats {{ $tables->whereIn('table_id', $otherTables->all())->map(fn ($t) => 'T'.$t->table_number)->implode(', ') }})
              @endif
            </option>
          @endforeach
        </select>

        <button type="submit" class="btn btn-solid" data-testid="floor-seat-reservation-{{ $table->table_id }}">Seat booking</button>
      </form>
    @endif

    @if ($held->isNotEmpty())
      <form method="POST" action="{{ route('staff.tables.release-reservation', $table) }}" class="floor-drawer-form">
        @csrf
        @method('DELETE')
        <label for="release-reservation-{{ $table->table_id }}">Give this table back</label>
        <select id="release-reservation-{{ $table->table_id }}" name="reservation_id" data-testid="floor-release-reservation-select-{{ $table->table_id }}">
          @foreach ($held as $reservation)
            <option value="{{ $reservation->reservation_id }}">{{ $reservation->slot?->slot_time }} &middot; {{ $reservation->reference_code }}</option>
          @endforeach
        </select>

        <button type="submit" class="btn btn-ghost" data-testid="floor-release-reservation-{{ $table->table_id }}">Release booking</button>
      </form>
    @endif

    @if ($offered->isNotEmpty())
      <form method="POST" action="{{ route('staff.tables.assign-reservation', $table) }}" class="floor-drawer-form">
        @csrf
        <label for="assign-reservation-{{ $table->table_id }}">Give this table to a booking</label>
        <select id="assign-reservation-{{ $table->table_id }}" name="reservation_id" data-testid="floor-assign-reservation-select-{{ $table->table_id }}">
          @foreach ($offered as $reservation)
            @php($on = $reservation->visits->whereNull('closed_at')->pluck('table_id'))
            <option value="{{ $reservation->reservation_id }}">
              {{ $reservation->slot?->slot_time }} &middot; {{ $reservation->reference_code }} &middot; {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('guest', $reservation->party_size) }}
              @if ($reservation->status === \App\Enums\ReservationStatus::Requested)
                (not approved yet)
              @elseif ($on->isNotEmpty())
                (adds to {{ $tables->whereIn('table_id', $on->all())->map(fn ($t) => 'T'.$t->table_number)->implode(', ') }})
              @endif
            </option>
          @endforeach
        </select>

        <button type="submit" class="btn btn-outline" data-testid="floor-assign-reservation-{{ $table->table_id }}">Assign booking</button>
      </form>
    @endif
  @endif

  @if ($table->status === \App\Enums\TableStatus::Available)
    <form method="POST" action="{{ route('staff.tables.seat', $table) }}" class="floor-drawer-form">
      @csrf
      <label for="guest-count-{{ $table->table_id }}">Walk-in guests</label>
      <input type="number" min="1" max="20" id="guest-count-{{ $table->table_id }}" name="guest_count" data-testid="floor-seat-guests-{{ $table->table_id }}">

      <button type="submit" class="btn btn-solid" data-testid="floor-seat-{{ $table->table_id }}">Seat walk-in</button>
    </form>
  @elseif ($table->status === \App\Enums\TableStatus::Occupied)
    @php($candidates = $tables->where('status', \App\Enums\TableStatus::Occupied)->where('table_id', '!=', $table->table_id))
    <form method="POST" action="{{ route('staff.tables.clear') }}" class="floor-drawer-form">
      @csrf
      <input type="hidden" name="table_ids[]" value="{{ $table->table_id }}">

      @if ($candidates->isNotEmpty())
        <fieldset>
          <legend>Clear together with</legend>
          @foreach ($candidates as $other)
            <label>
              <input type="checkbox" name="table_ids[]" value="{{ $other->table_id }}" data-testid="floor-clear-group-{{ $table->table_id }}-{{ $other->table_id }}">
              Table {{ $other->table_number }}@if ($other->section) &middot; {{ $other->section }}@endif
            </label>
          @endforeach
        </fieldset>
      @endif

      <label>
        <input type="checkbox" name="force" value="1" data-testid="floor-clear-force-{{ $table->table_id }}">
        Confirm even with active orders
      </label>

      <button type="submit" class="btn btn-ghost" data-testid="floor-clear-{{ $table->table_id }}">Clear table</button>
    </form>
  @endif
</details>
