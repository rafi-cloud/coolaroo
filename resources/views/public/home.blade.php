<x-layouts.public
    title="Coolaroo Restaurant & Bistro — Taste something new"
    description="Coolaroo Restaurant & Bistro. Wood-fired pizza, burgers and fresh seafood. Scan the QR code at your table for the menu, or book ahead online."
>
  {{-- Hero Section --}}
  <section class="hero" data-testid="home-hero">
    <img class="hero-img" src="{{ asset('images/hero-1.jpg') }}" alt="A table of wood-fired dishes at Coolaroo">
    <div class="hero-shade"></div>

    <div class="wrap hero-inner">
      <h1>TASTE SOMETHING NEW</h1>
      <p>Wood-fired, hand-made, and served hot since 2005</p>
      <div class="hero-actions">
        <a class="btn btn-amber" href="{{ url('/#menu') }}" data-testid="home-hero-menu">See the menu</a>
        @if(app(\App\Services\SettingService::class)->getBool('ai_enabled', true))
          <a class="btn btn-white" href="{{ route('meal-builder') }}" data-testid="home-hero-meal-builder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="width:16px;height:16px;margin-right:6px;display:inline-block;vertical-align:-2px;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>Build a meal
          </a>
        @endif
      </div>
    </div>

    <div class="dots" aria-hidden="true"><span class="on"></span><span></span><span></span></div>
  </section>

  {{-- Action Tiles --}}
  <section class="tiles" data-testid="home-tiles">
    <a class="tile" href="{{ url('/#menu') }}" data-testid="home-tile-menu">
      <img src="{{ asset('images/tile-menu.jpg') }}" alt="A plate of pasta from the Coolaroo menu">
      <span class="tile-cap"><span class="tile-title">OUR MENU</span><span class="tile-sub">View our specialities</span></span>
    </a>

    <a class="tile" href="{{ url('/#table-order-info') }}" data-testid="home-tile-table">
      <img src="{{ asset('images/tile-table.jpg') }}" alt="A set table in the Coolaroo dining room">
      <span class="tile-cap"><span class="tile-title">ORDER AT YOUR TABLE</span><span class="tile-sub">Scan the code at your table</span></span>
    </a>

    <a class="tile" href="{{ url('/meal-builder') }}" data-testid="home-tile-meal-builder">
      <img src="{{ asset('images/tile-interior.jpg') }}" alt="The Coolaroo dining room">
      <span class="tile-cap"><span class="tile-title">PLAN YOUR MEAL</span><span class="tile-sub">Let our assistant build it</span></span>
    </a>
  </section>

  {{-- About Section --}}
  <section class="section wrap" id="about" data-testid="home-about">
    <div class="about">
      <div class="about-figure">
        <img src="{{ asset('images/about-kitchen.jpg') }}" alt="Head chef plating dishes on the pass">
        <span class="play" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5l12 7-12 7z"/></svg>
        </span>
      </div>

      <div>
        <div class="rule"></div>
        <h2>Some words about us</h2>
        <p class="sub">A wood-fired kitchen on Sydney Road, cooking the way we'd cook at home.</p>
        <p>Coolaroo started in 2005 with one oven, six tables and a short menu we could do properly. Twenty years on the room is bigger, but the rule hasn't changed: buy well, cook it simply, send it out hot.</p>
        <p>The dough proves for two days. The fish comes in each morning off the boats. The pasta is rolled out the back before service, and whatever the kitchen runs out of is gone from the menu for the night &mdash; we'd rather tell you than fake it.</p>
        <img class="sig" src="{{ asset('images/signature.svg') }}" alt="Signature of the head chef">
      </div>
    </div>
  </section>

  {{-- Menu Section --}}
  <section class="section menu-section" id="menu" data-testid="home-menu-section">
    <div class="wrap">
      <div class="center">
        <div class="rule center"></div>
        <h2 class="section-title">Our Daily Menu</h2>
      </div>

      {{-- Category filter navigation --}}
      <nav class="menu-filter" aria-label="Menu categories" data-testid="home-menu-filter">
        <a class="on" href="{{ url('/#menu') }}" data-testid="home-filter-featured">Featured</a>
        @if($hasSpecials)
          <a href="{{ url('/menu?category=specials') }}" data-testid="home-filter-specials">Specials</a>
        @endif
        @foreach($categories as $category)
          <a href="{{ url('/menu?category=' . $category->category_id) }}" data-testid="home-filter-category-{{ $category->category_id }}">
            {{ $category->category_name }}
          </a>
        @endforeach
      </nav>

      {{-- Specials offer block, hidden when there are none --}}
      @if($topSpecial)
        @php
          $specialSize = $topSpecial['size'];
          $specialItem = $specialSize->menuItem;
        @endphp
        <div class="offer offer-single" data-testid="home-offer-block">
          <img class="offer-img" src="{{ $specialItem->image_url ? asset($specialItem->image_url) : asset('images/offer-burger.jpg') }}" alt="{{ $specialItem->item_name }}">
          <div class="offer-shade"></div>

          <div class="offer-copy">
            <p class="kicker">
              SPECIAL OFFER
              @if($topSpecial['ends_at'])
                &middot; UNTIL {{ strtoupper($topSpecial['ends_at']->format('l')) }}
              @endif
            </p>
            <h3>{{ $specialItem->item_name }} @money($specialSize->sale_price)</h3>
            <p class="items">{{ $specialItem->description }} &mdash; usually @money($specialSize->price)</p>
            <a class="btn btn-white" href="{{ url('/#reserve') }}" data-testid="home-offer-reserve">Reserve now</a>
          </div>
        </div>
      @endif

      {{-- Featured items grid (up to 12 items) --}}
      <div class="menu-grid" data-testid="home-featured-grid">
        @forelse($featuredItems as $item)
          @php
            $activeSizes = $item->sizes;
            $lowestSize = $activeSizes->first();
            $hasMultipleSizes = $activeSizes->count() > 1;
            $isOnSale = $lowestSize && app(\App\Services\SpecialsService::class)->isSaleActive($lowestSize);
          @endphp
          <article class="dish @if(! $item->is_available) is-soldout @endif" data-testid="home-dish-{{ $item->item_id }}">
            <span class="thumb">
              <img src="{{ $item->image_url ? asset($item->image_url) : asset('images/dish-burger.jpg') }}" alt="{{ $item->item_name }}">
            </span>
            <div class="dish-body">
              <div class="dish-head">
                <h4>{{ $item->item_name }}</h4>
                <span class="lead"></span>
                <span class="price">
                  @if(! $item->is_available)
                    @if($lowestSize)
                      @money($lowestSize->price)
                    @endif
                  @elseif($isOnSale)
                    <s>@money($lowestSize->price)</s> @money($lowestSize->sale_price)
                  @elseif($hasMultipleSizes)
                    <small>from</small> @money($lowestSize->price)
                  @elseif($lowestSize)
                    @money($lowestSize->price)
                  @endif
                </span>
              </div>
              <p>{{ $item->description }}</p>
              @if(! $item->is_available)
                <p class="tags"><span class="soldout" data-testid="home-dish-soldout-{{ $item->item_id }}">Sold out tonight</span></p>
              @elseif($item->dietaryTags->isNotEmpty())
                <p class="tags">
                  @foreach($item->dietaryTags as $tag)
                    <span class="tag" title="{{ $tag->tag_name }}">{{ strtoupper(str_starts_with(strtoupper($tag->tag_name), 'GF') ? 'GF' : substr($tag->tag_name, 0, 1)) }}</span>
                  @endforeach
                </p>
              @endif
            </div>
          </article>
        @empty
          <p data-testid="home-featured-empty">Check back soon for our featured specials.</p>
        @endforelse
      </div>

      <div class="menu-cta">
        <a class="btn btn-outline" href="{{ url('/menu') }}" data-testid="home-view-full-menu">View full menu</a>
        <p class="menu-note">At your table? Scan the QR code to order and pay from your phone.</p>
      </div>
    </div>
  </section>

  {{-- Reviews Section --}}
  <section class="section wrap" id="reviews" data-testid="home-reviews-section">
    <div class="center">
      <div class="rule center"></div>
      <h2 class="section-title">What our diners say</h2>
    </div>

    <div class="reviews @if(! $showRatingCard) no-rating-card @endif" data-testid="home-reviews-container">
      {{-- Rating summary card: shown when count >= public_rating_min_count --}}
      @if($showRatingCard && $ratingStats)
        <div class="rating-card" data-testid="home-rating-card">
          <p class="rating-big">{{ $ratingStats['overall_avg'] }}<span>/5</span></p>
          <div class="rating-row">
            <span class="rating-label">Food</span>
            <span class="stars" role="img" aria-label="Food rated {{ $ratingStats['food_avg'] }} out of 5">
              <span class="stars-fill" style="width:{{ $ratingStats['food_pct'] }}%"></span>
            </span>
            <strong>{{ $ratingStats['food_avg'] }}</strong>
          </div>
          <div class="rating-row">
            <span class="rating-label">Service</span>
            <span class="stars" role="img" aria-label="Service rated {{ $ratingStats['service_avg'] }} out of 5">
              <span class="stars-fill" style="width:{{ $ratingStats['service_pct'] }}%"></span>
            </span>
            <strong>{{ $ratingStats['service_avg'] }}</strong>
          </div>
          <p class="rating-count">Based on {{ $ratingStats['count'] }} {{ \Illuminate\Support\Str::plural('review', $ratingStats['count']) }} from diners</p>
        </div>
      @endif

      <div>
        <p class="featured-label">Featured reviews</p>
        <div class="review-grid" data-testid="home-featured-reviews-grid">
          @forelse($featuredReviews as $feedback)
            @php
              $author = $feedback->publicAuthor();
              $avgRating = $feedback->averageRating();
              $starPct = round(($avgRating / 5) * 100);
            @endphp
            <article class="review" data-testid="home-review-{{ $feedback->order_id }}">
              <span class="stars stars-sm" role="img" aria-label="Rated {{ $avgRating }} out of 5">
                <span class="stars-fill" style="width:{{ $starPct }}%"></span>
              </span>
              <blockquote>
                <p>{{ $feedback->comment }}</p>
              </blockquote>
              <p class="review-by" data-testid="home-review-by-{{ $feedback->order_id }}">
                {{ $author }} &middot; {{ $feedback->submitted_at?->format('F Y') }}
              </p>
            </article>
          @empty
            <p data-testid="home-reviews-empty">Reviews will appear here as diners share their experiences.</p>
          @endforelse
        </div>

        <p class="reviews-all-link">
          <a href="{{ route('reviews.index') }}" class="btn btn-outline" data-testid="home-reviews-view-all">
            Read all reviews
          </a>
        </p>
      </div>
    </div>
  </section>

  {{-- Reservation Wizard --}}
  <x-reserve />

  {{-- Section Anchor Hooks for subsequent Phase 6 tasks --}}
  <div id="table-order-info" class="visually-hidden" aria-hidden="true"></div>
</x-layouts.public>
