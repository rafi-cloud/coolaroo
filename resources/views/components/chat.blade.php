@props(['enabled' => null])
@php
    $enabled = $enabled ?? app(\App\Services\SettingService::class)->getBool('ai_enabled', true);
@endphp
@if ($enabled)
<div class="chat" data-chat>
  <button type="button" class="chat-launcher" id="chat-launcher" aria-expanded="false" aria-controls="chat-panel" data-testid="chat-open">
    <span class="chat-status-dot" aria-hidden="true"></span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.9-5a8.4 8.4 0 0 1-.8-3.6 8.4 8.4 0 0 1 8.4-8.4 8.4 8.4 0 0 1 8.5 8z"/></svg>
    <span>AI Dining Assistant</span>
  </button>

  <section class="chat-panel" id="chat-panel" aria-labelledby="chat-title" hidden data-testid="chat-panel">
    <header class="chat-head">
      <div class="chat-bot-avatar" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
      </div>
      <div class="chat-head-info">
        <h2 id="chat-title">Coolaroo AI Dining Assistant</h2>
        <span class="chat-head-sub"><span class="chat-status-dot" aria-hidden="true"></span> Online &bull; Live menu knowledge</span>
      </div>
      <a class="chat-build" href="{{ route('meal-builder') }}" data-testid="chat-build-a-meal">Build a meal &rarr;</a>
      <button type="button" class="chat-close" id="chat-close" data-testid="chat-close">
        <span class="visually-hidden">Close the menu assistant</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <div class="chat-log" id="chat-log" role="log" aria-live="polite" data-testid="chat-log">
      <div class="chat-msg chat-msg-bot">
        <p style="margin:0 0 .4rem;">Hi! I'm your AI Dining Assistant. Ask me anything about our dishes, prices, dietary options (GF, vegan, etc.), allergens, or today's chef specials.</p>
        <div class="chat-starters">
          <span class="chat-starter-title">Suggested questions:</span>
          <div class="chat-starter-list">
            <button type="button" class="chat-starter-btn" data-question="What are your vegetarian options?">🌱 Vegetarian options</button>
            <button type="button" class="chat-starter-btn" data-question="Do you have gluten-free dishes?">🌾 Gluten-free dishes?</button>
            <button type="button" class="chat-starter-btn" data-question="What are today's specials?">🥩 Any specials today?</button>
            <button type="button" class="chat-starter-btn" data-question="What drinks or wines do you recommend?">🍷 Drink recommendations</button>
          </div>
        </div>
      </div>
    </div>

    <form class="chat-form" id="chat-form" action="{{ route('ai.chat') }}" method="post">
      @csrf
      <label class="visually-hidden" for="chat-message">Your question</label>
      <input type="text" id="chat-message" name="message" maxlength="500" autocomplete="off" required placeholder="Ask about ingredients, diet, or specials..." data-testid="chat-input">
      <button type="submit" class="btn btn-orange" id="chat-send" data-testid="chat-send">Send</button>
    </form>

    <p class="chat-note" data-testid="chat-disclaimer">{{ \App\Services\AiMenuService::ALLERGEN_DISCLAIMER }}</p>
  </section>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var launcher = document.getElementById('chat-launcher');
  var panel = document.getElementById('chat-panel');
  var close = document.getElementById('chat-close');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-message');
  var send = document.getElementById('chat-send');
  var log = document.getElementById('chat-log');
  var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var history = [];

  function open(isOpen) {
    panel.hidden = !isOpen;
    launcher.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) { input.focus(); } else { launcher.focus(); }
  }

  function bubble(text, who) {
    var node = document.createElement('p');
    node.className = 'chat-msg chat-msg-' + who;
    node.textContent = text;
    log.appendChild(node);
    log.scrollTop = log.scrollHeight;
    return node;
  }

  function showTyping() {
    var node = document.createElement('div');
    node.className = 'chat-typing';
    node.id = 'chat-typing-bubble';
    node.innerHTML = '<span></span><span></span><span></span>';
    log.appendChild(node);
    log.scrollTop = log.scrollHeight;
    return node;
  }

  function chips(items) {
    if (!items || items.length === 0) {
      return;
    }

    var list = document.createElement('ul');
    list.className = 'chat-chips';

    items.forEach(function (item) {
      var entry = document.createElement('li');
      var link = document.createElement('a');
      link.className = 'chat-chip';
      link.href = '{{ route('menu.index') }}#item-' + item.item_id;
      link.textContent = item.name + ' · $' + Number(item.price).toFixed(2);
      entry.appendChild(link);
      list.appendChild(entry);
    });

    log.appendChild(list);
    log.scrollTop = log.scrollHeight;
  }

  launcher.addEventListener('click', function () { open(panel.hidden); });
  close.addEventListener('click', function () { open(false); });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) { open(false); }
  });

  document.addEventListener('click', function (e) {
    var starter = e.target.closest('.chat-starter-btn');
    if (starter) {
      var q = starter.getAttribute('data-question');
      if (q) {
        input.value = q;
        form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var question = input.value.trim();
    if (question === '') { return; }

    bubble(question, 'you');
    input.value = '';
    send.disabled = true;
    var typing = showTyping();

    fetch(form.action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token
      },
      body: JSON.stringify({ message: question, history: history })
    })
    .then(function (response) {
      if (response.status === 503) {
        throw new Error('Our menu assistant is busy right now. Please try again in a moment.');
      }
      if (!response.ok) {
        throw new Error('Sorry, something went wrong. Please try again.');
      }
      return response.json();
    })
    .then(function (data) {
      send.disabled = false;
      if (typing && typing.parentNode) { typing.parentNode.removeChild(typing); }
      bubble(data.answer, 'bot');
      chips(data.items);

      history.push({ role: 'user', content: question });
      history.push({ role: 'assistant', content: data.answer });
      history = history.slice(-10);
    })
    .catch(function (error) {
      send.disabled = false;
      if (typing && typing.parentNode) { typing.parentNode.removeChild(typing); }
      bubble(error.message, 'bot');
    });
  });
});
</script>
@endpush
@endif
