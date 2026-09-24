<x-dynamic-component
    :component="$table ? 'layouts.customer' : 'layouts.public'"
    :table-label="$tableLabel"
    title="Menu — Coolaroo Restaurant & Bistro"
    description="Browse our wood-fired pizzas, burgers, fresh seafood, and bistro favourites."
>
  <div class="wrap menu-head">
    <h1>Our Menu</h1>
    <p class="sub">Hand-made dishes, wood-fired mains and fresh local ingredients.</p>

    @if (session('error'))
      <div class="auth-error" role="alert" style="margin-block:1rem">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span>{{ session('error') }}</span>
      </div>
    @endif
    @if ($errors->any())
      <div class="auth-error" role="alert" style="margin-block:1rem">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span>{{ $errors->first() }}</span>
      </div>
    @endif

    @if(app(\App\Services\SettingService::class)->getBool('ai_enabled', true))
      <div class="menu-ai-banner" data-testid="menu-ai-banner">
        <div class="menu-ai-banner-content">
          <span class="menu-ai-badge">AI Assistant</span>
          <strong>Want a personalised meal recommendation?</strong>
          <span>Let our smart dining assistant build the perfect meal tailored to your budget and dietary preferences.</span>
        </div>
        <a href="{{ route('meal-builder') }}" class="btn btn-sm btn-orange" data-testid="menu-banner-meal-builder">Try Meal Builder &rarr;</a>
      </div>
    @endif

    {{-- Category tabs --}}
    <nav class="cat-tabs" aria-label="Menu categories" data-testid="menu-cat-tabs">
      <a class="cat-tab @if($selectedCategory === 'all') active @endif" href="{{ route('menu.index', array_merge(request()->except(['category', 'page']), ['category' => 'all'])) }}" data-testid="menu-tab-all">All</a>
      @if($hasSpecials)
        <a class="cat-tab @if($selectedCategory === 'specials') active @endif" href="{{ route('menu.index', array_merge(request()->except(['category', 'page']), ['category' => 'specials'])) }}" data-testid="menu-tab-specials">Specials</a>
      @endif
      @foreach($categories as $cat)
        <a class="cat-tab @if($selectedCategory == $cat->category_id) active @endif" href="{{ route('menu.index', array_merge(request()->except(['category', 'page']), ['category' => $cat->category_id])) }}" data-testid="menu-tab-{{ $cat->category_id }}">{{ $cat->category_name }}</a>
      @endforeach
    </nav>
  </div>

  {{-- Filter drawer for Dietary & Allergens --}}
  <div class="wrap">
    <details class="menu-filter-drawer" @if(!empty($selectedDietary) || !empty($selectedAllergens) || $search) open @endif data-testid="menu-filters">
      <summary data-testid="menu-filters-toggle">Filter by Dietary &amp; Allergens</summary>
      <form method="get" action="{{ route('menu.index') }}" data-testid="menu-filter-form">
        @if($selectedCategory !== 'all')
          <input type="hidden" name="category" value="{{ $selectedCategory }}">
        @endif
        <div class="filter-grid">
          <div class="filter-col">
            <h4>Search</h4>
            <label for="menu-search-input" class="visually-hidden">Search dishes</label>
            <input type="text" id="menu-search-input" name="q" value="{{ $search }}" placeholder="Search dishes..." data-testid="menu-search-input">
          </div>
          <div class="filter-col">
            <h4>Dietary Requirements</h4>
            <div class="filter-tags">
              @foreach($dietaryTags as $tag)
                <label class="filter-checkbox" data-testid="menu-dietary-label-{{ $tag->dietary_tag_id }}">
                  <input type="checkbox" name="dietary[]" value="{{ $tag->dietary_tag_id }}" @checked(in_array($tag->dietary_tag_id, $selectedDietary)) data-testid="menu-dietary-{{ $tag->dietary_tag_id }}">
                  {{ $tag->tag_name }}
                </label>
              @endforeach
            </div>
          </div>
          <div class="filter-col">
            <h4>Exclude Allergens</h4>
            <div class="filter-tags">
              @foreach($allergens as $allergen)
                <label class="filter-checkbox" data-testid="menu-allergen-label-{{ $allergen->allergen_id }}">
                  <input type="checkbox" name="exclude_allergen[]" value="{{ $allergen->allergen_id }}" @checked(in_array($allergen->allergen_id, $selectedAllergens)) data-testid="menu-allergen-{{ $allergen->allergen_id }}">
                  {{ $allergen->allergen_name }}
                </label>
              @endforeach
            </div>
          </div>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn btn-orange" data-testid="menu-filter-apply">Apply filters</button>
          <a href="{{ route('menu.index') }}" class="btn btn-ghost" data-testid="menu-filter-clear">Clear all</a>
        </div>
      </form>
    </details>

    {{-- Allergen disclaimer --}}
    <aside class="allergen-disclaimer" role="note" data-testid="menu-allergen-disclaimer">
      <strong>Allergy Notice:</strong> Please inform our staff of any serious allergies before ordering. Allergen labels reflect ingredients in each dish and its add-on options; however, our kitchen handles nuts, seafood, gluten and dairy, and cross-contact may occur.
    </aside>
  </div>

  {{-- Menu items grid --}}
  <div class="wrap">
    <div class="order-grid" data-testid="menu-grid">
      @forelse($items as $item)
        @php
          $activeSizes = $item->sizes;
          $lowestSize = $activeSizes->first();
          $hasMultipleSizes = $activeSizes->count() > 1;
          $isOnSale = $lowestSize && app(\App\Services\SpecialsService::class)->isSaleActive($lowestSize);
          $hasNutrition = $item->calories_kcal || $item->protein_g || $item->carbohydrates_g || $item->fat_g;
        @endphp
        <article id="item-{{ $item->item_id }}" class="order-card @if(! $item->is_available) is-soldout @endif" data-testid="menu-card-{{ $item->item_id }}">
          <div class="order-card-media">
            <img src="{{ $item->image_url ? asset($item->image_url) : asset('images/dish-burger.jpg') }}" alt="{{ $item->item_name }}">
          </div>
          <div class="order-card-body">
            <div class="order-card-head">
              <h4>{{ $item->item_name }}</h4>
              <span class="price">
                @if(! $item->is_available && $lowestSize)
                  @money($lowestSize->price)
                @elseif($isOnSale)
                  <s>@money($lowestSize->price)</s> @money($lowestSize->sale_price)
                @elseif($hasMultipleSizes)
                  <small>from</small> @money($lowestSize->price)
                @elseif($lowestSize)
                  @money($lowestSize->price)
                @endif
              </span>
            </div>
            <p class="desc">{{ $item->description }}</p>

            @if($item->dietaryTags->isNotEmpty())
              <p class="tags">
                @foreach($item->dietaryTags as $tag)
                  <span class="tag">{{ $tag->tag_name }}</span>
                @endforeach
              </p>
            @endif

            {{-- Nutrition Information --}}
            @if($hasNutrition)
              <div class="nutrition-pill" data-testid="menu-nutrition-{{ $item->item_id }}">
                @if($item->calories_kcal) {{ (int) $item->calories_kcal }} kcal @endif
                @if($item->protein_g) &middot; P: {{ (int) $item->protein_g }}g @endif
                @if($item->carbohydrates_g) &middot; C: {{ (int) $item->carbohydrates_g }}g @endif
                @if($item->fat_g) &middot; F: {{ (int) $item->fat_g }}g @endif
              </div>
            @endif

            @if(! $item->is_available)
              <span class="unavailable-tag" data-testid="menu-soldout-{{ $item->item_id }}">Sold out</span>
            @endif

            <div class="order-card-foot">
              <button
                type="button"
                class="btn btn-ghost btn-sm"
                data-open-modal="item-modal-{{ $item->item_id }}"
                data-testid="menu-item-{{ $item->item_id }}"
                data-item-id="{{ $item->item_id }}"
              >
                View details
              </button>
            </div>
          </div>
        </article>

        {{-- Item detail modal. Outside the card on purpose: the card's
             hover transform would otherwise become the containing block for
             the modal's position:fixed scrim, trapping it inside the card. --}}
        <x-menu.item-modal
          :item="$item"
          :has-table-context="(bool) $table"
          :qr-ordering-enabled="$qrOrderingEnabled"
        />
      @empty
        <p class="menu-empty" data-testid="menu-empty">No dishes found matching your selected filters.</p>
      @endforelse
    </div>
  </div>

  @push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      function calcTotal(modal) {
        if (!modal) return;
        var sizeInput = modal.querySelector('input[name="size_id"]:checked') || modal.querySelector('input[name="size_id"][type="hidden"]');
        var sizePrice = sizeInput ? parseFloat(sizeInput.getAttribute('data-price') || 0) : 0;

        var addonTotal = 0;
        modal.querySelectorAll('input[name="add_on_option_ids[]"]:checked').forEach(function (opt) {
          addonTotal += parseFloat(opt.getAttribute('data-price') || 0);
        });

        var qtyInput = modal.querySelector('input[name="quantity"]');
        var qty = qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;
        var total = (sizePrice + addonTotal) * qty;

        var totalDisplay = modal.querySelector('.btn-price-calc');
        if (totalDisplay) {
          totalDisplay.textContent = '$' + total.toFixed(2);
        }
      }

      function openModal(modal) {
        if (!modal) return false;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        calcTotal(modal);
        return true;
      }

      function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        document.body.style.overflow = '';
        // Drop the #item-N so the same link can reopen this dish later:
        // navigating to an identical hash fires no hashchange event.
        if (/^#item-\d+$/.test(window.location.hash)) {
          history.replaceState(null, '', window.location.pathname + window.location.search);
        }
      }

      // Deep link from the AI assistant: /menu#item-12 opens that dish
      function openFromHash() {
        var match = /^#item-(\d+)$/.exec(window.location.hash);
        if (!match) return;

        var card = document.getElementById('item-' + match[1]);
        if (card) {
          card.scrollIntoView({ block: 'center' });
        }

        openModal(document.getElementById('item-modal-' + match[1]));
      }

      // Open modal
      document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-open-modal]');
        if (openBtn) {
          e.preventDefault();
          openModal(document.getElementById(openBtn.getAttribute('data-open-modal')));
        }
      });

      // Close modal
      document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
          e.preventDefault();
          closeModal(document.getElementById(closeBtn.getAttribute('data-close-modal')));
        } else if (e.target.classList.contains('modal-scrim')) {
          closeModal(e.target);
        }
      });

      openFromHash();
      window.addEventListener('hashchange', openFromHash);

      // Escape key closes modals
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.modal-scrim.open').forEach(closeModal);
        }
      });

      // Quantity buttons
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-qty-action]');
        if (!btn) return;
        var modal = btn.closest('.modal-scrim');
        var input = modal ? modal.querySelector('input[name="quantity"]') : null;
        if (!input) return;

        var current = parseInt(input.value, 10) || 1;
        if (btn.getAttribute('data-qty-action') === 'plus') {
          input.value = Math.min(99, current + 1);
        } else if (btn.getAttribute('data-qty-action') === 'minus') {
          input.value = Math.max(1, current - 1);
        }
        calcTotal(modal);
      });

      // Inputs change (size, addons, quantity)
      document.addEventListener('change', function (e) {
        var modal = e.target.closest('.modal-scrim');
        if (!modal) return;

        // Enforce max-select on addon groups
        if (e.target.name === 'add_on_option_ids[]') {
          var group = e.target.closest('.addon-group');
          if (group) {
            var maxSelect = parseInt(group.getAttribute('data-max'), 10) || 1;
            var checked = group.querySelectorAll('input[name="add_on_option_ids[]"]:checked');
            if (maxSelect === 1 && e.target.checked) {
              checked.forEach(function (chk) {
                if (chk !== e.target) chk.checked = false;
              });
            } else if (checked.length > maxSelect) {
              e.target.checked = false;
            }
          }
        }

        calcTotal(modal);
      });
    });
  </script>
  @endpush
</x-dynamic-component>
