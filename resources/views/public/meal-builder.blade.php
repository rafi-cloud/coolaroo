<x-layouts.public
    title="Plan your meal"
    description="Tell us your budget, party size and dietary needs, and our menu assistant will put a meal together from today's menu."
>
  <section class="section">
    <div class="wrap">
      <div class="rule"></div>
      <h1 class="section-title">Plan your meal</h1>
      <p class="lead">Give us a budget and a few preferences. We only ever suggest dishes that are on the menu today, and we price every suggestion ourselves.</p>

      <form class="mb-form" id="meal-builder-form" action="{{ route('ai.meal-builder') }}" method="post" data-testid="meal-builder-form">
        @csrf

        <div class="mb-grid">
          <div class="mb-field">
            <label for="mb-budget">Budget for the table (AUD)</label>
            <input type="number" id="mb-budget" name="budget" min="1" max="1000" step="0.01" value="60" required data-testid="meal-builder-budget">
          </div>

          <div class="mb-field">
            <label for="mb-party-size">How many people?</label>
            <select id="mb-party-size" name="party_size" required data-testid="meal-builder-party-size">
              @for ($i = 1; $i <= 10; $i++)
                <option value="{{ $i }}" @selected($i === 2)>{{ $i }}</option>
              @endfor
            </select>
          </div>
        </div>

        @if ($dietaryTags->isNotEmpty())
          <fieldset class="mb-fieldset">
            <legend>Dietary needs</legend>
            <div class="mb-tags">
              @foreach ($dietaryTags as $tag)
                <label class="mb-tag" for="mb-diet-{{ $tag->dietary_tag_id }}">
                  <input type="checkbox" id="mb-diet-{{ $tag->dietary_tag_id }}" name="dietary[]" value="{{ $tag->tag_name }}" data-testid="meal-builder-diet-{{ $tag->dietary_tag_id }}">
                  {{ $tag->tag_name }}
                </label>
              @endforeach
            </div>
          </fieldset>
        @endif

        <div class="mb-field">
          <label for="mb-preferences">Anything else? (optional)</label>
          <textarea id="mb-preferences" name="preferences" rows="2" maxlength="300" placeholder="Something to share, nothing too spicy, a cold beer" data-testid="meal-builder-preferences"></textarea>
        </div>

        <button type="submit" class="btn btn-orange" id="meal-builder-submit" data-testid="meal-builder-submit">Build my meal</button>
      </form>

      <p class="mb-status" id="meal-builder-status" role="status" hidden data-testid="meal-builder-status"></p>

      <div class="mb-results" id="meal-builder-results" data-testid="meal-builder-results"></div>

      <p class="mb-note" data-testid="meal-builder-disclaimer">{{ \App\Services\AiMenuService::ALLERGEN_DISCLAIMER }}</p>
    </div>
  </section>

  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('meal-builder-form');
    var submit = document.getElementById('meal-builder-submit');
    var status = document.getElementById('meal-builder-status');
    var results = document.getElementById('meal-builder-results');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function say(message) {
      status.textContent = message;
      status.hidden = message === '';
    }

    function money(amount) {
      return '$' + Number(amount).toFixed(2);
    }

    function el(tag, className, text) {
      var node = document.createElement(tag);
      if (className) { node.className = className; }
      if (text !== undefined) { node.textContent = text; }
      return node;
    }

    function lineText(line) {
      var text = line.quantity + '× ' + line.name;
      if (line.size_name) { text += ' (' + line.size_name + ')'; }
      if (line.options && line.options.length) { text += ' + ' + line.options.join(', '); }
      return text;
    }

    function addToCart(lines, button) {
      button.disabled = true;
      say('Adding to your cart…');

      var queue = lines.reduce(function (chain, line) {
        return chain.then(function () {
          var body = new FormData();
          body.append('item_id', line.item_id);
          body.append('size_id', line.size_id);
          body.append('quantity', line.quantity);
          (line.add_on_option_ids || []).forEach(function (id) {
            body.append('add_on_option_ids[]', id);
          });

          return fetch('{{ route('cart.lines.store') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: body
          });
        });
      }, Promise.resolve());

      queue.then(function () {
        window.location = '{{ route('cart.index') }}';
      }).catch(function () {
        button.disabled = false;
        say('We could not add that to your cart. Please try again.');
      });
    }

    function card(suggestion, index, canAddToCart) {
      var article = el('article', 'mb-card');
      article.setAttribute('data-testid', 'meal-builder-suggestion-' + index);

      article.appendChild(el('h2', 'mb-card-title', suggestion.title));
      article.appendChild(el('p', 'mb-why', suggestion.rationale));

      var list = el('ul', 'mb-lines');
      suggestion.lines.forEach(function (line) {
        var item = el('li');
        item.appendChild(el('span', 'mb-line-name', lineText(line)));
        item.appendChild(el('span', 'mb-line-total', money(line.line_total)));
        list.appendChild(item);
      });
      article.appendChild(list);

      var total = el('p', 'mb-total');
      total.appendChild(el('span', null, 'Total'));
      total.appendChild(el('strong', null, money(suggestion.total)));
      article.appendChild(total);

      if (canAddToCart) {
        var button = el('button', 'btn btn-orange', 'Add to cart');
        button.type = 'button';
        button.setAttribute('data-testid', 'meal-builder-add-' + index);
        button.addEventListener('click', function () { addToCart(suggestion.lines, button); });
        article.appendChild(button);
      } else {
        article.appendChild(el('p', 'mb-scan', 'Scan the QR code on your table to order these.'));
      }

      return article;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      results.innerHTML = '';
      submit.disabled = true;
      say('Putting a meal together…');

      var data = new FormData(form);
      var payload = {
        budget: data.get('budget'),
        party_size: data.get('party_size'),
        preferences: data.get('preferences'),
        dietary: data.getAll('dietary[]')
      };

      fetch(form.action, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token
        },
        body: JSON.stringify(payload)
      })
      .then(function (response) {
        if (response.status === 503) {
          throw new Error('Our menu assistant is busy right now. Please try again in a moment.');
        }
        if (!response.ok) {
          throw new Error('Please check your budget and party size, then try again.');
        }
        return response.json();
      })
      .then(function (data) {
        submit.disabled = false;

        if (!data.suggestions || data.suggestions.length === 0) {
          say("We could not put a meal together from today’s menu. Try a larger budget, or fewer dietary filters.");
          return;
        }

        say(data.summary || '');

        data.suggestions.forEach(function (suggestion, index) {
          results.appendChild(card(suggestion, index, data.can_add_to_cart));
        });
      })
      .catch(function (error) {
        submit.disabled = false;
        say(error.message);
      });
    });
  });
  </script>
  @endpush
</x-layouts.public>
