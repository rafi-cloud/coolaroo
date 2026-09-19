@php
    $groups = [
        'Overview' => [
            ['id' => 'admin-dashboard', 'label' => 'Dashboard', 'url' => '/admin', 'match' => ['admin'],
             'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        ],
        'Service' => [
            ['id' => 'admin-floor', 'label' => 'Floor view', 'url' => '/staff/floor', 'match' => ['staff/floor*', 'staff/tables*'],
             'icon' => '<circle cx="12" cy="12" r="8"/><path d="M8 12h8M12 8v8"/>'],
            ['id' => 'admin-orders', 'label' => 'Orders', 'url' => '/admin/orders', 'match' => ['admin/orders*'],
             'icon' => '<path d="M6 4h12l1 16H5z"/><path d="M9 8h6"/><path d="M9 12h6"/>'],
            ['id' => 'admin-refunds', 'label' => 'Refunds', 'url' => '/admin/refunds', 'match' => ['admin/refunds*'],
             'icon' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>'],
            ['id' => 'admin-reservations', 'label' => 'Reservations', 'url' => '/staff/reservations', 'match' => ['staff/reservations*'],
             'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>'],
        ],
        'Catalogue' => [
            ['id' => 'admin-menu-items', 'label' => 'Menu items', 'url' => '/admin/menu-items', 'match' => ['admin/menu-items*'],
             'icon' => '<path d="M4 6h9M4 12h9M4 18h9"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/>'],
            ['id' => 'admin-categories', 'label' => 'Categories & tags', 'url' => '/admin/categories', 'match' => ['admin/categories*'],
             'icon' => '<path d="M3 7l3-3h5l2 2h8v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
            ['id' => 'admin-tables', 'label' => 'Tables & QR', 'url' => '/admin/tables', 'match' => ['admin/tables*'],
             'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/><rect x="14" y="14" width="7" height="7" rx="1.3"/>'],
        ],
        'People' => [
            ['id' => 'admin-customers', 'label' => 'Customers', 'url' => '/admin/customers', 'match' => ['admin/customers*'],
             'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>'],
            ['id' => 'admin-staff', 'label' => 'Staff', 'url' => '/admin/staff', 'match' => ['admin/staff*'],
             'icon' => '<circle cx="9" cy="8" r="3.4"/><path d="M2 20c0-3.7 3.1-6 7-6s7 2.3 7 6"/><path d="M17 8.5a3 3 0 0 0 0-1M18 14c2.4.6 4 2.4 4 5"/>'],
            ['id' => 'admin-feedback', 'label' => 'Feedback', 'url' => '/admin/feedback', 'match' => ['admin/feedback*'],
             'icon' => '<path d="M12 4l2.3 4.9 5.2.7-3.8 3.7 1 5.3-4.7-2.6-4.7 2.6 1-5.3L4.5 9.6l5.2-.7z"/>'],
        ],
        'Insight' => [
            ['id' => 'admin-reports', 'label' => 'Reports', 'url' => '/admin/reports', 'match' => ['admin/reports*'],
             'icon' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
            ['id' => 'admin-audit-log', 'label' => 'Audit log', 'url' => '/admin/audit-log', 'match' => ['admin/audit-log*'],
             'icon' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/>'],
            ['id' => 'admin-settings', 'label' => 'Settings', 'url' => '/admin/settings', 'match' => ['admin/settings*'],
             'icon' => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2 2 2 0 1 1-4 0 1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 15a2 2 0 1 1 0-4 1.7 1.7 0 0 0 1.4-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 4a2 2 0 1 1 4 0a1.7 1.7 0 0 0 2.8 1.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.7 1.7 0 0 0 21 11a2 2 0 1 1 0 4z"/>'],
        ],
    ];
@endphp
<nav class="nav" aria-label="Admin">
  @foreach ($groups as $label => $items)
    <p class="nav-label">{{ $label }}</p>
    @foreach ($items as $item)
      <x-dashboard.nav-item :item="$item" />
    @endforeach
  @endforeach
</nav>