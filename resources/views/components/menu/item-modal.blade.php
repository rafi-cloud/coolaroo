@props([
    'item',
    'hasTableContext' => false,
    'qrOrderingEnabled' => true,
])

@php
    $activeSizes = $item->sizes->where('is_active', true)->values();
    $lowestSize = $activeSizes->first();
    $specials = app(\App\Services\SpecialsService::class);
    $hasNutrition = $item->calories_kcal !== null || $item->protein_g !== null || $item->carbohydrates_g !== null || $item->fat_g !== null;
    $canOrder = $hasTableContext && $qrOrderingEnabled && $item->is_available;
@endphp

<div
  class="modal-scrim"
  id="item-modal-{{ $item->item_id }}"
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-{{ $item->item_id }}"
  data-testid="item-modal-{{ $item->item_id }}"
>
  <div class="item-modal" data-testid="item-modal-box-{{ $item->item_id }}">
    <div class="item-modal-wrap">
      <button
        class="item-modal-close"
        type="button"
        data-close-modal="item-modal-{{ $item->item_id }}"
        aria-label="Close"
        data-testid="item-modal-close-{{ $item->item_id }}"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2B1A10" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
      </button>
      <img src="{{ $item->image_url ? asset($item->image_url) : asset('images/dish-burger.jpg') }}" alt="{{ $item->item_name }}">
    </div>

    <div class="item-modal-body">
      @if($canOrder)
        <form method="POST" action="{{ route('cart.lines.store') }}" class="item-modal-form" id="form-item-{{ $item->item_id }}" data-testid="item-modal-form-{{ $item->item_id }}">
          @csrf
          <input type="hidden" name="item_id" value="{{ $item->item_id }}">
      @endif

      <h3 id="modal-title-{{ $item->item_id }}" data-testid="item-modal-title-{{ $item->item_id }}">{{ $item->item_name }}</h3>

      <div class="modal-head-meta">
        <p class="price" id="display-price-{{ $item->item_id }}" data-testid="item-modal-price-{{ $item->item_id }}">
          @if(! $item->is_available)
            <span class="unavailable-tag">Sold out</span>
          @elseif($lowestSize)
            @php($isSale = $specials->isSaleActive($lowestSize))
            @if($isSale)
              <s>@money($lowestSize->price)</s> @money($lowestSize->sale_price)
            @else
              @money($lowestSize->price)
            @endif
          @endif
        </p>

        @if($item->dietaryTags->isNotEmpty())
          <div class="modal-dietary-tags" data-testid="item-modal-dietary-{{ $item->item_id }}">
            @foreach($item->dietaryTags as $tag)
              <span class="tag" title="{{ $tag->tag_name }}">{{ $tag->tag_name }}</span>
            @endforeach
          </div>
        @endif
      </div>

      <p class="desc" data-testid="item-modal-desc-{{ $item->item_id }}">{{ $item->description }}</p>

      @if($item->allergens->isNotEmpty())
        <div class="modal-allergens" data-testid="item-modal-allergens-{{ $item->item_id }}">
          <strong>Allergens:</strong> {{ $item->allergens->pluck('allergen_name')->join(', ') }}
        </div>
      @endif

      {{-- FR34: Nutrition Information --}}
      @if($hasNutrition)
        <div class="item-modal-nutrition" data-testid="item-modal-nutrition-{{ $item->item_id }}">
          <h4>Nutrition (per serve)</h4>
          <div class="nutrition-grid">
            @if($item->calories_kcal !== null)
              <div class="nutri-item">
                <span class="nutri-val">{{ (int) $item->calories_kcal }}</span>
                <span class="nutri-lbl">Calories</span>
              </div>
            @endif
            @if($item->protein_g !== null)
              <div class="nutri-item">
                <span class="nutri-val">{{ (int) $item->protein_g }}g</span>
                <span class="nutri-lbl">Protein</span>
              </div>
            @endif
            @if($item->carbohydrates_g !== null)
              <div class="nutri-item">
                <span class="nutri-val">{{ (int) $item->carbohydrates_g }}g</span>
                <span class="nutri-lbl">Carbs</span>
              </div>
            @endif
            @if($item->fat_g !== null)
              <div class="nutri-item">
                <span class="nutri-val">{{ (int) $item->fat_g }}g</span>
                <span class="nutri-lbl">Fat</span>
              </div>
            @endif
          </div>
        </div>
      @endif

      {{-- Size Selection --}}
      @if($activeSizes->count() > 1)
        <fieldset class="item-modal-section" data-testid="item-modal-sizes-{{ $item->item_id }}">
          <legend>Size</legend>
          <div class="size-options">
            @foreach($activeSizes as $idx => $size)
              @php($isSale = $specials->isSaleActive($size))
              @php($chargedPrice = $isSale ? $size->sale_price : $size->price)
              <label class="size-option-label" for="size-{{ $size->size_id }}">
                <input
                  type="radio"
                  id="size-{{ $size->size_id }}"
                  name="size_id"
                  value="{{ $size->size_id }}"
                  data-price="{{ $chargedPrice }}"
                  data-original-price="{{ $size->price }}"
                  data-is-sale="{{ $isSale ? '1' : '0' }}"
                  data-modal-id="{{ $item->item_id }}"
                  @checked($idx === 0)
                  data-testid="size-option-{{ $size->size_id }}"
                  required
                >
                <span class="size-name">{{ $size->size_name }}</span>
                <span class="size-price">
                  @if($isSale)
                    <s>@money($size->price)</s> @money($size->sale_price)
                  @else
                    @money($size->price)
                  @endif
                </span>
              </label>
            @endforeach
          </div>
        </fieldset>
      @elseif($lowestSize)
        @php($isSale = $specials->isSaleActive($lowestSize))
        @php($chargedPrice = $isSale ? $lowestSize->sale_price : $lowestSize->price)
        <input
          type="hidden"
          name="size_id"
          value="{{ $lowestSize->size_id }}"
          data-price="{{ $chargedPrice }}"
          data-original-price="{{ $lowestSize->price }}"
          data-is-sale="{{ $isSale ? '1' : '0' }}"
          data-modal-id="{{ $item->item_id }}"
          id="size-{{ $lowestSize->size_id }}"
          data-testid="size-option-{{ $lowestSize->size_id }}"
        >
      @endif

      {{-- Add-on Groups (BR16) --}}
      @if($item->addOnGroups->isNotEmpty())
        <div class="item-modal-add-ons" data-testid="item-modal-add-ons-{{ $item->item_id }}">
          @foreach($item->addOnGroups as $group)
            <fieldset class="addon-group" data-group-id="{{ $group->group_id }}" data-min="{{ $group->min_select }}" data-max="{{ $group->max_select }}">
              <legend>
                <span class="group-title">{{ $group->group_name }}</span>
                @if($group->is_required || $group->min_select > 0)
                  <span class="group-badge required">Required (min {{ $group->min_select }})</span>
                @else
                  <span class="group-badge optional">Optional (up to {{ $group->max_select }})</span>
                @endif
              </legend>
              <div class="addon-options-list">
                @foreach($group->options as $option)
                  <label class="addon-option-label @if(! $option->is_available) is-unavailable @endif" for="option-{{ $option->option_id }}">
                    <input
                      type="checkbox"
                      id="option-{{ $option->option_id }}"
                      name="add_on_option_ids[]"
                      value="{{ $option->option_id }}"
                      data-price="{{ $option->price_delta }}"
                      data-group-id="{{ $group->group_id }}"
                      data-max-select="{{ $group->max_select }}"
                      data-modal-id="{{ $item->item_id }}"
                      @disabled(! $option->is_available)
                      data-testid="addon-option-{{ $option->option_id }}"
                    >
                    <span class="addon-name">{{ $option->option_name }}</span>
                    <span class="addon-price">
                      @if($option->price_delta > 0)
                        +@money($option->price_delta)
                      @else
                        Free
                      @endif
                    </span>
                  </label>
                @endforeach
              </div>
            </fieldset>
          @endforeach
        </div>
      @endif

      {{-- Quantity & Special Request --}}
      @if($canOrder)
        <div class="qty-row">
          <label for="qty-{{ $item->item_id }}">Quantity</label>
          <div class="qty-control">
            <button
              class="qty-btn"
              type="button"
              data-qty-action="minus"
              data-target="qty-{{ $item->item_id }}"
              data-modal-id="{{ $item->item_id }}"
              aria-label="Decrease quantity"
              data-testid="qty-minus-{{ $item->item_id }}"
            >&minus;</button>
            <input
              type="number"
              id="qty-{{ $item->item_id }}"
              name="quantity"
              value="1"
              min="1"
              max="99"
              class="qty-num-input"
              data-modal-id="{{ $item->item_id }}"
              data-testid="item-modal-qty-{{ $item->item_id }}"
              required
            >
            <button
              class="qty-btn"
              type="button"
              data-qty-action="plus"
              data-target="qty-{{ $item->item_id }}"
              data-modal-id="{{ $item->item_id }}"
              aria-label="Increase quantity"
              data-testid="qty-plus-{{ $item->item_id }}"
            >+</button>
          </div>
        </div>

        <div class="item-field">
          <label for="special-request-{{ $item->item_id }}">Special request (optional, max 200 characters)</label>
          <textarea
            id="special-request-{{ $item->item_id }}"
            name="special_request"
            rows="2"
            maxlength="200"
            placeholder="e.g. dressing on the side, extra crispy"
            data-testid="item-modal-special-request-{{ $item->item_id }}"
          ></textarea>
        </div>
      @endif

      {{-- Footer / Action Button --}}
      <div class="item-modal-foot">
        @if(! $item->is_available)
          <div class="modal-state-notice soldout" data-testid="item-modal-soldout-{{ $item->item_id }}">
            <p><strong>Sold out tonight</strong> &mdash; This dish is currently unavailable.</p>
            <button type="button" class="btn btn-ghost" data-close-modal="item-modal-{{ $item->item_id }}">Close</button>
          </div>
        @elseif(! $hasTableContext)
          <div class="modal-state-notice no-table" data-testid="item-modal-no-table-{{ $item->item_id }}">
            <div class="notice-body">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
              <p>Scan the QR code on your table to order (BR57).</p>
            </div>
            <button type="button" class="btn btn-ghost" data-close-modal="item-modal-{{ $item->item_id }}">Close</button>
          </div>
        @elseif(! $qrOrderingEnabled)
          <div class="modal-state-notice paused" data-testid="item-modal-paused-{{ $item->item_id }}">
            <p><strong>Ordering paused</strong> &mdash; Online ordering is temporarily paused by staff (BR58).</p>
            <button type="button" class="btn btn-ghost" data-close-modal="item-modal-{{ $item->item_id }}">Close</button>
          </div>
        @else
          <button
            class="btn btn-orange btn-add-to-cart"
            type="submit"
            id="btn-add-{{ $item->item_id }}"
            data-testid="item-modal-add-to-cart-{{ $item->item_id }}"
          >
            <span>Add to cart</span>
            <span class="btn-price-calc" id="btn-price-{{ $item->item_id }}">$0.00</span>
          </button>
        @endif
      </div>

      @if($canOrder)
        </form>
      @endif
    </div>
  </div>
</div>
