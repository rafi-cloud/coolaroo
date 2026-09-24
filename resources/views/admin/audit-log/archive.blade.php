<x-layouts.admin title="Archived Records" page-title="Historical Data Archives" page-sub="Read-only repository of archived entity snapshots and deactivation audits">
  <div class="card" data-testid="admin-archive-page">
        <nav class="report-nav" aria-label="Audit navigation" style="margin-bottom:1.5rem;" data-testid="admin-audit-tabs">
      <a href="{{ route('admin.audit-log.index') }}" class="report-tab" data-testid="admin-audit-tab-events">
        Audit Events
      </a>
      <a href="{{ route('admin.archive.index') }}" class="report-tab is-active" data-testid="admin-audit-tab-archive">
        Archived Records
      </a>
    </nav>

        <form method="GET" action="{{ route('admin.archive.index') }}" class="kds-filters" style="margin-bottom:1.5rem; gap:.6rem; flex-wrap:wrap;" data-testid="admin-archive-filter-form">
            <div>
        <label for="archive-entity-filter" class="sr-only">Entity Type</label>
        <select id="archive-entity-filter" name="entity_name" data-testid="admin-archive-filter-entity" style="padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px; background:var(--white); color:var(--ink);">
          <option value="">All Archived Entities</option>
          @foreach ($entities as $ent)
            <option value="{{ $ent }}" @selected(($filters['entity_name'] ?? '') === $ent)>
              {{ ucwords(str_replace('_', ' ', $ent)) }}
            </option>
          @endforeach
        </select>
      </div>

            <div style="flex:1; min-width:240px;">
        <label for="archive-search-input" class="sr-only">Search</label>
        <input type="text" id="archive-search-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search record key or snapshot content..." style="width:100%; padding:.45rem .8rem; font-size:.84rem; border:1px solid var(--line); border-radius:6px;" data-testid="admin-archive-search-input">
      </div>

      <button type="submit" class="btn btn-ghost" data-testid="admin-archive-filter-submit">Filter</button>
      @if (! empty($filters['entity_name']) || ! empty($filters['search']))
        <a href="{{ route('admin.archive.index') }}" class="btn btn-outline" data-testid="admin-archive-filter-reset">Reset</a>
      @endif
    </form>

        <div class="table-scroll">
      <table class="table" data-testid="admin-archive-table">
        <thead>
          <tr>
            <th>Archived At</th>
            <th>Entity Type</th>
            <th>Record Key / ID</th>
            <th>Archived By</th>
            <th>Deactivation Reason</th>
            <th>Status</th>
            <th>JSON Snapshot Data</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($archives as $archive)
            <tr data-testid="admin-archive-row-{{ $archive->history_id }}">
              <td style="white-space:nowrap; font-size:.82rem; color:var(--cancelled);">
                @auDateTime($archive->archived_at)
              </td>
              <td>
                <strong style="color:var(--ink); font-size:.86rem;">
                  {{ ucwords(str_replace('_', ' ', $archive->entity_name)) }}
                </strong>
              </td>
              <td>
                <span class="pill" style="font-size:.78rem; font-weight:600; background:var(--sand); padding:.2rem .55rem; border-radius:4px;" data-testid="admin-archive-key-{{ $archive->history_id }}">
                  {{ $archive->record_id }}
                </span>
              </td>
              <td>
                @if ($archive->auditLog?->staff)
                  <div style="font-size:.84rem; font-weight:600; color:var(--ink);">
                    {{ $archive->auditLog->staff->full_name }}
                  </div>
                  <div style="font-size:.72rem; color:var(--cancelled);">
                    {{ $archive->auditLog->staff->role?->role_name ?? 'staff' }}
                  </div>
                @else
                  <span class="muted text-xs">System</span>
                @endif
              </td>
              <td style="font-size:.82rem; color:var(--body); max-width:240px;">
                {{ $archive->auditLog?->details['reason'] ?? '—' }}
              </td>
              <td>
                <span class="badge" style="background:#FEE2E2; color:#991B1B; font-weight:700; font-size:.72rem; padding:.2rem .6rem; border-radius:100px;">
                  {{ strtoupper($archive->status) }}
                </span>
              </td>
              <td style="max-width:320px;">
                <details data-testid="admin-archive-snapshot-{{ $archive->history_id }}">
                  <summary style="font-size:.78rem; color:var(--orange-dark); cursor:pointer; font-weight:600;">
                    View Record JSON Snapshot
                  </summary>
                  <pre style="margin-top:.4rem; padding:.6rem; background:#F8FAFC; border:1px solid var(--line); border-radius:4px; font-size:.72rem; max-height:180px; overflow:auto; font-family:monospace; line-height:1.4;">{{ json_encode($archive->record_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center muted" style="padding:2.5rem;" data-testid="admin-archive-empty">
                No archived record snapshots found matching your filter criteria.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

        @if ($archives->hasPages())
      <div style="margin-top:1.5rem;" data-testid="admin-archive-pagination">
        {{ $archives->links() }}
      </div>
    @endif
  </div>
</x-layouts.admin>
