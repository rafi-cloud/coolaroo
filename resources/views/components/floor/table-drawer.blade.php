@props(['table', 'tables'])
<details class="kds-drawer floor-table-drawer" data-testid="floor-table-drawer-{{ $table->table_id }}">
  {{-- the drawer holds one action, and which one depends on the table --}}
  <summary>
    @if ($table->status === \App\Enums\TableStatus::Available)
      Seat guests
    @elseif ($table->status === \App\Enums\TableStatus::Occupied)
      Clear table
    @else
      Table details
    @endif
  </summary>

  @if ($table->status === \App\Enums\TableStatus::Available)
    @php($candidates = $tables->where('status', \App\Enums\TableStatus::Available)->where('table_id', '!=', $table->table_id))
    <form method="POST" action="{{ route('staff.tables.seat', $table) }}" class="floor-drawer-form">
      @csrf
      <label for="guest-count-{{ $table->table_id }}">Guests</label>
      <input type="number" min="1" max="20" id="guest-count-{{ $table->table_id }}" name="guest_count" data-testid="floor-seat-guests-{{ $table->table_id }}">

      @if ($candidates->isNotEmpty())
        <fieldset>
          <legend>Seat together with</legend>
          @foreach ($candidates as $other)
            <label>
              <input type="checkbox" name="other_table_ids[]" value="{{ $other->table_id }}" data-testid="floor-seat-group-{{ $table->table_id }}-{{ $other->table_id }}">
              Table {{ $other->table_number }}
            </label>
          @endforeach
        </fieldset>
      @endif

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
              Table {{ $other->table_number }}
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
  @else
    <p class="muted" data-testid="floor-reserved-note-{{ $table->table_id }}">Reserved &mdash; no walk-in actions here.</p>
  @endif
</details>
