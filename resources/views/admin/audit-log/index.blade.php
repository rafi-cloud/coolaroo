<x-layouts.admin title="Audit Log" page-title="Audit Log &amp; Compliance" page-sub="Immutable log of system modifications, administrative actions, and security events">
  <div class="card" data-testid="admin-audit-log-page">
        <nav class="report-nav" aria-label="Audit navigation" style="margin-bottom:1.5rem;" data-testid="admin-audit-tabs">
      <a href="{{ route('admin.audit-log.index') }}" class="report-tab is-active" data-testid="admin-audit-tab-events">
        Audit Events
      </a>
      <a href="{{ route('admin.archive.index') }}" class="report-tab" data-testid="admin-audit-tab-archive">
        Archived Records
      </a>
    </nav>

        <form method="GET" action="{{ route('admin.audit-log.index') }}" class="kds-filters" style="margin-bottom:1.5rem; gap:.6rem; flex-wrap:wrap;" data-testid="admin-audit-filter-form">
            <div>
        <label for="audit-action-type" class="sr-only">Action Type</label>
        <select id="audit-action-type" name="action_type" data-testid="admin-audit-filter-action" style="padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px; background:var(--white); color:var(--ink);">
          <option value="">All Action Types</option>
          @foreach ($actionTypes as $type)
            <option value="{{ $type }}" @selected(($filters['action_type'] ?? '') === $type)>
              {{ ucwords(str_replace('_', ' ', $type)) }}
            </option>
          @endforeach
        </select>
      </div>

            <div>
        <label for="audit-entity-name" class="sr-only">Entity Name</label>
        <select id="audit-entity-name" name="entity_name" data-testid="admin-audit-filter-entity" style="padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px; background:var(--white); color:var(--ink);">
          <option value="">All Entities</option>
          @foreach ($entityNames as $ent)
            <option value="{{ $ent }}" @selected(($filters['entity_name'] ?? '') === $ent)>
              {{ ucwords(str_replace('_', ' ', $ent)) }}
            </option>
          @endforeach
        </select>
      </div>

            <div>
        <label for="audit-staff-actor" class="sr-only">Actor</label>
        <select id="audit-staff-actor" name="staff_id" data-testid="admin-audit-filter-staff" style="padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px; background:var(--white); color:var(--ink);">
          <option value="">All Staff Actors</option>
          @foreach ($staffMembers as $staff)
            <option value="{{ $staff->staff_id }}" @selected(($filters['staff_id'] ?? '') == $staff->staff_id)>
              {{ $staff->full_name }}
            </option>
          @endforeach
        </select>
      </div>

            <div style="display:flex; align-items:center; gap:.35rem;">
        <label for="audit-date-from" style="font-size:.82rem; color:var(--cancelled);">From:</label>
        <input type="date" id="audit-date-from" name="from" value="{{ $filters['from'] ?? '' }}" style="padding:.45rem .6rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-audit-filter-from">
      </div>

      <div style="display:flex; align-items:center; gap:.35rem;">
        <label for="audit-date-to" style="font-size:.82rem; color:var(--cancelled);">To:</label>
        <input type="date" id="audit-date-to" name="to" value="{{ $filters['to'] ?? '' }}" style="padding:.45rem .6rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-audit-filter-to">
      </div>

            <div style="flex:1; min-width:200px;">
        <label for="audit-search-input" class="sr-only">Search</label>
        <input type="text" id="audit-search-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search actor, reason, IP, entity ID..." style="width:100%; padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-audit-search-input">
      </div>

      <button type="submit" class="btn btn-ghost" data-testid="admin-audit-filter-submit">Filter</button>
      @if (! empty($filters['action_type']) || ! empty($filters['entity_name']) || ! empty($filters['staff_id']) || ! empty($filters['from']) || ! empty($filters['to']) || ! empty($filters['search']))
        <a href="{{ route('admin.audit-log.index') }}" class="btn btn-outline" data-testid="admin-audit-filter-reset">Reset</a>
      @endif
    </form>

        <div class="table-scroll">
      <table class="table" data-testid="admin-audit-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Entity &amp; Target</th>
            <th>IP Address</th>
            <th>Event Details / Diff</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr data-testid="admin-audit-row-{{ $log->log_id }}">
              <td style="white-space:nowrap; font-size:.82rem; color:var(--cancelled);">
                @auDateTime($log->logged_at)
              </td>
              <td>
                @if ($log->staff)
                  <strong style="color:var(--ink); font-size:.85rem;">{{ $log->staff->full_name }}</strong>
                  <div style="font-size:.72rem; color:var(--cancelled); text-transform:uppercase;">
                    Staff &middot; {{ $log->staff->role?->role_name ?? 'staff' }}
                  </div>
                @elseif ($log->customer)
                  <strong style="color:var(--ink); font-size:.85rem;">{{ $log->customer->full_name }}</strong>
                  <div style="font-size:.72rem; color:var(--cancelled); text-transform:uppercase;">Customer</div>
                @else
                  <span class="badge" style="background:#F3F4F6; color:#4B5563; font-size:.72rem; padding:.15rem .5rem; border-radius:100px;">System</span>
                @endif
              </td>
              <td>
                <span class="badge" style="background:#E0E7FF; color:#3730A3; font-size:.75rem; font-weight:600; padding:.2rem .6rem; border-radius:4px;" data-testid="admin-audit-action-{{ $log->log_id }}">
                  {{ ucwords(str_replace('_', ' ', $log->action_type)) }}
                </span>
              </td>
              <td>
                <span style="font-weight:600; font-size:.84rem; color:var(--ink);">{{ ucwords(str_replace('_', ' ', $log->entity_name)) }}</span>
                @if ($log->entity_id)
                  <span class="pill" style="font-size:.75rem; background:var(--sand); padding:.15rem .45rem; border-radius:3px; margin-left:.25rem;">
                    #{{ $log->entity_id }}
                  </span>
                @endif

                @if ($log->archive)
                  <div style="margin-top:.25rem;">
                    <a href="{{ route('admin.archive.index', ['search' => $log->archive->record_id]) }}" class="text-xs" style="color:var(--orange-dark); text-decoration:none; font-weight:600;" data-testid="admin-audit-archive-link-{{ $log->log_id }}">
                      View Snapshot &rarr;
                    </a>
                  </div>
                @endif
              </td>
              <td style="font-size:.82rem; color:var(--cancelled); font-family:monospace;">
                {{ $log->ip_address ?? '—' }}
              </td>
              <td style="max-width:380px;">
                @php
                  $details = $log->details;
                @endphp

                @if ($log->action_type === 'ai_request')
                  <div style="font-size:.82rem; color:var(--body);">
                    <strong>Feature:</strong> {{ ucwords(str_replace('_', ' ', (string) ($details['feature'] ?? 'chat'))) }}<br>
                    <strong>Tokens:</strong> {{ number_format($details['tokens_in'] ?? 0) }} in &middot; {{ number_format($details['tokens_out'] ?? 0) }} out
                  </div>
                @elseif (is_array($details))
                  @if (! empty($details['reason']))
                    <div style="font-size:.82rem; margin-bottom:.3rem; color:var(--ink);">
                      <strong>Reason:</strong> {{ $details['reason'] }}
                    </div>
                  @endif

                  @if (isset($details['before']) || isset($details['after']))
                    <details style="margin-top:.2rem;">
                      <summary style="font-size:.78rem; color:var(--orange-dark); cursor:pointer; font-weight:600;">
                        View Before / After JSON
                      </summary>
                      <pre style="margin-top:.4rem; padding:.5rem; background:#F8FAFC; border:1px solid var(--line); border-radius:4px; font-size:.72rem; max-height:160px; overflow:auto; font-family:monospace; line-height:1.4;">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                  @elseif (! empty($details) && empty($details['reason']))
                    <details style="margin-top:.2rem;">
                      <summary style="font-size:.78rem; color:var(--orange-dark); cursor:pointer; font-weight:600;">
                        View Event Payload
                      </summary>
                      <pre style="margin-top:.4rem; padding:.5rem; background:#F8FAFC; border:1px solid var(--line); border-radius:4px; font-size:.72rem; max-height:160px; overflow:auto; font-family:monospace; line-height:1.4;">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                  @endif
                @else
                  <span class="muted text-xs">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center muted" style="padding:2.5rem;" data-testid="admin-audit-empty">
                No audit log records found matching your filter criteria.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

        @if ($logs->hasPages())
      <div style="margin-top:1.5rem;" data-testid="admin-audit-pagination">
        {{ $logs->links() }}
      </div>
    @endif
  </div>
</x-layouts.admin>
