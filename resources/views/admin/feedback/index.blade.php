<x-layouts.admin title="Feedback Moderation" page-title="Customer Feedback" page-sub="Review diner ratings, reply to comments, and moderate featured reviews">
  <div class="card" data-testid="admin-feedback-page">
    @if (session('status'))
      <div class="alert alert-success" role="status" style="margin-bottom:1.2rem;" data-testid="admin-feedback-status">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger" role="alert" style="margin-bottom:1.2rem;" data-testid="admin-feedback-errors">
        <ul style="margin:0; padding-left:1.2rem;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Metric Overview Cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom:1.5rem;" data-testid="admin-feedback-metrics">
      <div style="padding:1rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:8px;">
        <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--cancelled);">Total Reviews</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--ink); margin-top:.2rem;">{{ number_format($counts['total']) }}</div>
      </div>
      <div style="padding:1rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:8px;">
        <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--cancelled);">Visible on Site</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--served, #059669); margin-top:.2rem;">{{ number_format($counts['visible']) }}</div>
      </div>
      <div style="padding:1rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:8px;">
        <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--cancelled);">Featured Testimonials</div>
        <div style="font-size:1.6rem; font-weight:700; color:#D97706; margin-top:.2rem;">{{ number_format($counts['featured']) }}</div>
      </div>
      <div style="padding:1rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:8px;">
        <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--cancelled);">Hidden Reviews</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--cancelled, #DC2626); margin-top:.2rem;">{{ number_format($counts['hidden']) }}</div>
      </div>
      <div style="padding:1rem; background:var(--sand-light, #FAF5EE); border:1px solid var(--line); border-radius:8px;">
        <div style="font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:var(--cancelled);">Needs Reply</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--orange, #D97706); margin-top:.2rem;">{{ number_format($counts['unreplied']) }}</div>
      </div>
    </div>

    {{-- Filter Navigation and Search Bar --}}
    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; padding-bottom:1.2rem; border-bottom:1px solid var(--line); margin-bottom:1.5rem;" data-testid="admin-feedback-filter-bar">
      {{-- Status Filter Tabs --}}
      <nav class="report-nav" aria-label="Feedback status filter" style="margin:0;">
        @php
          $currentStatus = $filters['status'] ?? 'all';
        @endphp
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'all', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'all' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-all">All</a>
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'visible', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'visible' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-visible">Visible</a>
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'featured', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'featured' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-featured">Featured</a>
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'unreplied', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'unreplied' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-unreplied">Needs Reply</a>
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'replied', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'replied' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-replied">Replied</a>
        <a href="{{ route('admin.feedback.index', array_merge($filters, ['status' => 'hidden', 'page' => 1])) }}" class="report-tab {{ $currentStatus === 'hidden' ? 'is-active' : '' }}" data-testid="admin-feedback-tab-hidden">Hidden</a>
      </nav>

      {{-- Search & Rating Form --}}
      <form method="GET" action="{{ route('admin.feedback.index') }}" class="kds-filters" style="margin:0; gap:.6rem;" data-testid="admin-feedback-search-form">
        <input type="hidden" name="status" value="{{ $currentStatus }}">

        <label for="feedback-rating-filter" class="sr-only">Rating Filter</label>
        <select id="feedback-rating-filter" name="rating" data-testid="admin-feedback-rating-filter" style="padding:.45rem .8rem; font-size:.85rem; border:1px solid var(--line); border-radius:6px; background:var(--white); color:var(--ink);">
          <option value="">All Ratings</option>
          <option value="5" @selected(($filters['rating'] ?? '') == '5')>5 Stars ★★★★★</option>
          <option value="4" @selected(($filters['rating'] ?? '') == '4')>4 Stars ★★★★☆</option>
          <option value="3" @selected(($filters['rating'] ?? '') == '3')>3 Stars ★★★☆☆</option>
          <option value="2" @selected(($filters['rating'] ?? '') == '2')>2 Stars ★★☆☆☆</option>
          <option value="1" @selected(($filters['rating'] ?? '') == '1')>1 Star ★☆☆☆☆</option>
        </select>

        <label for="feedback-search-input" class="sr-only">Search</label>
        <input type="text" id="feedback-search-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Customer, order #, comment..." style="padding:.45rem .8rem; font-size:.85rem; border:1px solid var(--line); border-radius:6px; min-width:220px;" data-testid="admin-feedback-search-input">

        <button type="submit" class="btn btn-ghost" data-testid="admin-feedback-filter-submit">Filter</button>
        @if (! empty($filters['search']) || ! empty($filters['rating']) || ($filters['status'] ?? 'all') !== 'all')
          <a href="{{ route('admin.feedback.index') }}" class="btn btn-outline" data-testid="admin-feedback-filter-reset">Reset</a>
        @endif
      </form>
    </div>

    {{-- Reviews List --}}
    <ul class="reviews" data-testid="admin-feedback-list" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:1.2rem;">
      @forelse ($feedbackList as $f)
        <li class="review {{ $f->is_hidden ? 'is-hidden' : '' }} {{ ! $f->admin_reply ? 'is-pending' : '' }}" data-testid="admin-feedback-item-{{ $f->order_id }}" style="background:var(--white); border:1px solid {{ $f->is_hidden ? '#FCA5A5' : ($f->is_featured ? '#FDE68A' : 'var(--line)') }}; border-radius:10px; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
          <div class="review-body">
            {{-- Review Header Row --}}
            <div class="review-top" style="display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; margin-bottom:.6rem;">
              <strong style="font-size:1.05rem; color:var(--ink);" data-testid="admin-feedback-customer-{{ $f->order_id }}">
                {{ $f->customer ? $f->customer->full_name : 'Guest Diner' }}
              </strong>

              @if ($f->customer?->email)
                <span style="font-size:.78rem; color:var(--cancelled);" data-testid="admin-feedback-email-{{ $f->order_id }}">
                  ({{ $f->customer->email }})
                </span>
              @endif

              <a href="{{ route('admin.orders.show', $f->order_id) }}" style="font-size:.78rem; font-weight:600; text-decoration:none; color:var(--orange-dark); background:var(--sand-light, #FAF5EE); border:1px solid var(--line); padding:.2rem .55rem; border-radius:4px;" data-testid="admin-feedback-order-{{ $f->order_id }}">
                Order #{{ $f->order_id }}
              </a>

              {{-- Status Badges --}}
              @if ($f->is_featured)
                <span class="badge" style="background:#FEF3C7; color:#92400E; font-weight:700; font-size:.72rem; padding:.2rem .6rem; border-radius:100px;" data-testid="admin-feedback-featured-badge-{{ $f->order_id }}">
                  ★ Featured Testimonial
                </span>
              @endif

              @if ($f->is_hidden)
                <span class="badge" style="background:#FEE2E2; color:#991B1B; font-weight:700; font-size:.72rem; padding:.2rem .6rem; border-radius:100px;" data-testid="admin-feedback-hidden-badge-{{ $f->order_id }}">
                  Hidden from Public
                </span>
              @endif

              @if ($f->admin_reply)
                <span class="badge" style="background:#E0E7FF; color:#3730A3; font-weight:700; font-size:.72rem; padding:.2rem .6rem; border-radius:100px;" data-testid="admin-feedback-replied-badge-{{ $f->order_id }}">
                  Replied
                </span>
              @else
                <span class="badge" style="background:#F3F4F6; color:#4B5563; font-weight:600; font-size:.72rem; padding:.2rem .6rem; border-radius:100px;" data-testid="admin-feedback-unreplied-badge-{{ $f->order_id }}">
                  Awaiting Reply
                </span>
              @endif

              <span class="review-date" style="margin-left:auto; font-size:.8rem; color:var(--cancelled);" data-testid="admin-feedback-date-{{ $f->order_id }}">
                @auDateTime($f->submitted_at)
              </span>
            </div>

            {{-- Ratings Breakdown --}}
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:1.2rem; font-size:.85rem; margin-bottom:.6rem; color:var(--ink);">
              <div>
                <span style="color:var(--cancelled); margin-right:.3rem;">Food Quality:</span>
                <strong style="color:var(--amber, #D97706);" data-testid="admin-feedback-food-rating-{{ $f->order_id }}">
                  {{ str_repeat('★', $f->food_rating) }}{{ str_repeat('☆', 5 - $f->food_rating) }} ({{ $f->food_rating }}/5)
                </strong>
              </div>
              <div>
                <span style="color:var(--cancelled); margin-right:.3rem;">Service &amp; Atmosphere:</span>
                <strong style="color:var(--amber, #D97706);" data-testid="admin-feedback-service-rating-{{ $f->order_id }}">
                  {{ str_repeat('★', $f->service_rating) }}{{ str_repeat('☆', 5 - $f->service_rating) }} ({{ $f->service_rating }}/5)
                </strong>
              </div>
            </div>

            {{-- Comment --}}
            @if ($f->comment)
              <div class="review-text" style="font-size:.95rem; font-family:var(--serif); line-height:1.6; color:var(--ink); background:#FAF8F5; padding:.8rem 1rem; border-radius:6px; border-left:3px solid var(--line);" data-testid="admin-feedback-comment-{{ $f->order_id }}">
                &ldquo;{{ $f->comment }}&rdquo;
              </div>
            @else
              <div class="muted" style="font-size:.82rem; font-style:italic; padding:.4rem 0;" data-testid="admin-feedback-no-comment-{{ $f->order_id }}">
                No written comment provided with this rating.
              </div>
            @endif

            {{-- Hidden Reason Alert --}}
            @if ($f->is_hidden)
              <div style="margin-top:.75rem; padding:.6rem .9rem; background:#FFFBEB; border-left:3px solid #F59E0B; border-radius:4px; font-size:.82rem; color:#92400E;" data-testid="admin-feedback-hidden-reason-{{ $f->order_id }}">
                <strong>Moderation Reason:</strong> {{ $f->hidden_reason }}
              </div>
            @endif

            {{-- Existing Admin Reply Box --}}
            @if ($f->admin_reply)
              <div style="margin-top:.75rem; padding:.8rem 1rem; background:#F8FAFC; border-left:3px solid var(--orange); border-radius:0 6px 6px 0;" data-testid="admin-feedback-reply-box-{{ $f->order_id }}">
                <div style="display:flex; justify-content:space-between; font-size:.78rem; font-weight:600; color:var(--ink); margin-bottom:.35rem;">
                  <span>Management Response &middot; {{ $f->repliedBy?->full_name ?? 'Admin' }}</span>
                  <span style="color:var(--cancelled); font-weight:normal;">@auDateTime($f->replied_at)</span>
                </div>
                <div style="font-size:.88rem; color:var(--body); line-height:1.5;" data-testid="admin-feedback-reply-text-{{ $f->order_id }}">
                  {{ $f->admin_reply }}
                </div>
              </div>
            @endif

            {{-- Moderation Action Bar --}}
            <div class="review-actions" style="display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; margin-top:1rem; padding-top:.8rem; border-top:1px solid var(--line);">
              {{-- Feature / Unfeature Button --}}
              @if (! $f->is_hidden)
                <form method="POST" action="{{ route('admin.feedback.feature', $f) }}" style="display:inline;">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-xs {{ $f->is_featured ? 'btn-outline' : 'btn-ghost' }}" data-testid="admin-feedback-feature-btn-{{ $f->order_id }}" title="{{ $f->is_featured ? 'Remove from homepage featured list' : 'Display this review in public homepage reviews section' }}">
                    {{ $f->is_featured ? '★ Unfeature' : '☆ Feature on Home' }}
                  </button>
                </form>
              @else
                <button type="button" class="btn btn-xs btn-outline" disabled style="opacity:.45; cursor:not-allowed;" title="Hidden reviews cannot be featured on the homepage" data-testid="admin-feedback-feature-disabled-{{ $f->order_id }}">
                  ☆ Feature (Hidden)
                </button>
              @endif

              {{-- Hide / Unhide Toggle --}}
              @if ($f->is_hidden)
                <form method="POST" action="{{ route('admin.feedback.unhide', $f) }}" style="display:inline;">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-xs btn-outline" data-testid="admin-feedback-unhide-btn-{{ $f->order_id }}" title="Restore visibility of this review on the public site">
                    Restore to Public
                  </button>
                </form>
              @else
                <details style="position:relative; display:inline-block;">
                  <summary class="btn btn-xs btn-outline" style="cursor:pointer; display:inline-block;" data-testid="admin-feedback-hide-toggle-{{ $f->order_id }}">
                    Hide Review
                  </summary>
                  <div style="position:absolute; bottom:calc(100% + 6px); left:0; z-index:30; background:var(--white); border:1px solid var(--line); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:1rem; width:320px;">
                    <form method="POST" action="{{ route('admin.feedback.hide', $f) }}">
                      @csrf
                      @method('PATCH')
                      <div class="field" style="margin-bottom:.75rem;">
                        <label for="hide-reason-{{ $f->order_id }}" style="font-size:.8rem; font-weight:600; display:block; margin-bottom:.3rem;">
                          Mandatory Reason <span style="color:var(--danger)">*</span>
                        </label>
                        <input type="text" id="hide-reason-{{ $f->order_id }}" name="reason" required maxlength="255" placeholder="e.g. Offensive language, spam, or privacy violation" style="width:100%; padding:.45rem .6rem; font-size:.82rem; border:1px solid var(--line); border-radius:4px;" data-testid="admin-feedback-hide-reason-input-{{ $f->order_id }}">
                      </div>
                      <div style="display:flex; justify-content:flex-end; gap:.4rem;">
                        <button type="submit" class="btn btn-xs btn-primary" data-testid="admin-feedback-hide-confirm-{{ $f->order_id }}">
                          Confirm Hide
                        </button>
                      </div>
                    </form>
                  </div>
                </details>
              @endif

              {{-- Reply Button & Accordion --}}
              <details style="position:relative; display:inline-block;">
                <summary class="btn btn-xs btn-primary" style="cursor:pointer; display:inline-block;" data-testid="admin-feedback-reply-toggle-{{ $f->order_id }}">
                  {{ $f->admin_reply ? 'Edit Response' : 'Reply to Review' }}
                </summary>
                <div style="position:absolute; bottom:calc(100% + 6px); right:0; z-index:30; background:var(--white); border:1px solid var(--line); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:1rem; width:360px;">
                  <form method="POST" action="{{ route('admin.feedback.reply', $f) }}" data-testid="admin-feedback-reply-form-{{ $f->order_id }}">
                    @csrf
                    <div class="field" style="margin-bottom:.75rem;">
                      <label for="reply-text-{{ $f->order_id }}" style="font-size:.8rem; font-weight:600; display:block; margin-bottom:.3rem;">
                        Management Response
                      </label>
                      <textarea id="reply-text-{{ $f->order_id }}" name="reply" rows="4" required maxlength="1000" placeholder="Thank the guest or address their concerns..." style="width:100%; padding:.5rem; font-size:.85rem; border:1px solid var(--line); border-radius:4px;" data-testid="admin-feedback-reply-textarea-{{ $f->order_id }}">{{ old('reply', $f->admin_reply) }}</textarea>
                      <div style="font-size:.72rem; color:var(--cancelled); margin-top:.2rem;">Maximum 1,000 characters. Visible to the public.</div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:.4rem;">
                      <button type="submit" class="btn btn-xs btn-primary" data-testid="admin-feedback-reply-submit-{{ $f->order_id }}">
                        {{ $f->admin_reply ? 'Update Response' : 'Publish Response' }}
                      </button>
                    </div>
                  </form>
                </div>
              </details>
            </div>
          </div>
        </li>
      @empty
        <li style="padding:2.5rem; text-align:center; background:var(--sand-light, #FAF5EE); border:1px dashed var(--line); border-radius:8px; color:var(--cancelled);" data-testid="admin-feedback-empty">
          <p style="font-size:1.05rem; font-weight:600; color:var(--ink); margin-bottom:.3rem;">No feedback found</p>
          <p style="font-size:.85rem; margin:0;">There are no customer reviews matching your active filter criteria.</p>
        </li>
      @endforelse
    </ul>

    {{-- Pagination --}}
    @if ($feedbackList->hasPages())
      <div style="margin-top:1.5rem;" data-testid="admin-feedback-pagination">
        {{ $feedbackList->links() }}
      </div>
    @endif
  </div>
</x-layouts.admin>
