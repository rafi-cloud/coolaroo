@props(['status'])
<span class="status-pill status-pill-{{ $status->value }}" data-testid="order-status-badge">{{ ucfirst(str_replace('_', ' ', $status->value)) }}</span>
