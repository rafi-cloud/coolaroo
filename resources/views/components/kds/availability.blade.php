@props(['destination', 'items'])
<details class="kds-drawer" data-testid="kds-availability-drawer">
  <summary>Availability</summary>

  <ul class="kds-availability-list">
    @forelse ($items as $item)
      <li>
        <span>{{ $item->item_name }}</span>
        <form method="POST" action="{{ route('staff.menu-items.availability', $item) }}">
          @csrf
          @method('PATCH')
          <button
            type="submit"
            class="btn {{ $item->is_available ? 'btn-solid' : 'btn-ghost' }}"
            data-testid="kds-availability-item-{{ $item->item_id }}"
          >{{ $item->is_available ? 'Available' : 'Sold out' }}</button>
        </form>
      </li>

      @foreach ($item->addOnGroups as $group)
        @foreach ($group->options as $option)
          <li class="kds-availability-suboption">
            <span>{{ $item->item_name }} &mdash; {{ $option->option_name }}</span>
            <form method="POST" action="{{ route('staff.add-on-options.availability', $option) }}">
              @csrf
              @method('PATCH')
              <button
                type="submit"
                class="btn {{ $option->is_available ? 'btn-solid' : 'btn-ghost' }}"
                data-testid="kds-availability-option-{{ $option->option_id }}"
              >{{ $option->is_available ? 'Available' : 'Sold out' }}</button>
            </form>
          </li>
        @endforeach
      @endforeach
    @empty
      <li data-testid="kds-availability-empty">No items at this station.</li>
    @endforelse
  </ul>
</details>
