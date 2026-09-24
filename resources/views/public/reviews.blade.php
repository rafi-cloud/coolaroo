<x-layouts.public title="Reviews" description="What diners say about Coolaroo Restaurant &amp; Bistro — food and service ratings from real orders.">
<main id="top">
  <div class="wrap menu-head">
    <h1>What our diners say</h1>
    <p class="sub">Every review here comes from a diner who ordered and paid at the restaurant.</p>
  </div>

  <div class="wrap">
    @if ($showRatingCard && $ratingStats)
      <div class="reviews-summary" data-testid="reviews-rating-card">
        <p class="rating-big">{{ $ratingStats['overall_avg'] }}<span>/5</span></p>
        <div class="reviews-summary-rows">
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
      </div>
    @endif

    <div class="review-grid" data-testid="reviews-grid">
      @forelse ($reviews as $feedback)
        @php($avgRating = $feedback->averageRating())
        <article class="review" data-testid="review-{{ $feedback->order_id }}">
          <span class="stars stars-sm" role="img" aria-label="Rated {{ $avgRating }} out of 5">
            <span class="stars-fill" style="width:{{ round(($avgRating / 5) * 100) }}%"></span>
          </span>

          @if (filled($feedback->comment))
            <blockquote>
              <p>{{ $feedback->comment }}</p>
            </blockquote>
          @else
            <p class="review-no-comment">Rated without a written review.</p>
          @endif

          <p class="review-by" data-testid="review-by-{{ $feedback->order_id }}">
            {{ $feedback->publicAuthor() }} &middot; {{ $feedback->submitted_at?->format('F Y') }}
          </p>
        </article>
      @empty
        <p data-testid="reviews-empty">Reviews will appear here as diners share their experiences.</p>
      @endforelse
    </div>

    {{ $reviews->links() }}
  </div>
</main>
</x-layouts.public>
