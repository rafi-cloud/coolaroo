@props(['order'])
<div data-testid="feedback-form">
  <h2>Rate your visit</h2>
  <p>Tell us how the food and service were — it only takes a moment.</p>

  <form method="POST" action="{{ route('orders.feedback.store', $order) }}">
    @csrf

    <fieldset class="rating-field">
      <legend>Food</legend>
      <div class="rating-options">
        @for ($i = 1; $i <= 5; $i++)
          <label>
            <input type="radio" name="food_rating" value="{{ $i }}" required @checked((int) old('food_rating') === $i) data-testid="feedback-food-{{ $i }}">
            {{ $i }}
          </label>
        @endfor
      </div>
      @error('food_rating')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset class="rating-field">
      <legend>Service</legend>
      <div class="rating-options">
        @for ($i = 1; $i <= 5; $i++)
          <label>
            <input type="radio" name="service_rating" value="{{ $i }}" required @checked((int) old('service_rating') === $i) data-testid="feedback-service-{{ $i }}">
            {{ $i }}
          </label>
        @endfor
      </div>
      @error('service_rating')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </fieldset>

    <div class="auth-field">
      <label for="feedback-comment">Comments (optional)</label>
      <textarea id="feedback-comment" name="comment" class="wizard-textarea" maxlength="1000" data-testid="feedback-comment">{{ old('comment') }}</textarea>
      @error('comment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>

    <button class="btn btn-orange" type="submit" data-testid="feedback-submit">Submit feedback</button>
  </form>
</div>
