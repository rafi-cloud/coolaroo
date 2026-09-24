@php
    $role = auth('staff')->user()?->role?->role_name;

    $groups = [];

    if (in_array($role, ['waitstaff', 'admin'], true)) {
        $groups['Live service'] = [
            ['id' => 'staff-floor', 'label' => 'Floor view', 'url' => '/staff/floor', 'match' => ['staff/floor*', 'staff/tables*'],
             'icon' => '<circle cx="12" cy="12" r="8"/><path d="M8 12h8M12 8v8"/>'],
            ['id' => 'staff-reservations', 'label' => 'Reservations', 'url' => '/staff/reservations', 'match' => ['staff/reservations*'],
             'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>'],
        ];
    }

    if (in_array($role, ['kitchen', 'bar', 'admin'], true)) {
        $groups['Stations'] = [
            ['id' => 'staff-kds-kitchen', 'label' => 'Kitchen display', 'url' => '/staff/kds/kitchen', 'match' => ['staff/kds/kitchen*'],
             'icon' => '<path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/>'],
            ['id' => 'staff-kds-bar', 'label' => 'Bar display', 'url' => '/staff/kds/bar', 'match' => ['staff/kds/bar*'],
             'icon' => '<path d="M6 3h12l-5 8v7h3v3H8v-3h3v-7z"/>'],
        ];
    }
@endphp
<nav class="nav" aria-label="Staff">
  @foreach ($groups as $label => $items)
    <p class="nav-label">{{ $label }}</p>
    @foreach ($items as $item)
      <x-dashboard.nav-item :item="$item" />
    @endforeach
  @endforeach
</nav>