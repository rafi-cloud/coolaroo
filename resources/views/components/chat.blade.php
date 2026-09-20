@props(['enabled' => null])
@php
    $enabled = $enabled ?? app(\App\Services\SettingService::class)->getBool('ai_enabled', true);
@endphp
@if ($enabled)
<div class="chat" data-chat>
  <button type="button" class="chat-launcher" id="chat-launcher" aria-expanded="false" aria-controls="chat-panel" data-testid="chat-open">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.9-5a8.4 8.4 0 0 1-.8-3.6 8.4 8.4 0 0 1 8.4-8.4 8.4 8.4 0 0 1 8.5 8z"/></svg>
    <span>Ask about the menu</span>
  </button>

  <section class="chat-panel" id="chat-panel" aria-labelledby="chat-title" hidden data-testid="chat-panel">
    <header class="chat-head">
      <h2 id="chat-title">Menu assistant</h2>
      <a class="chat-build" href="{{ route('meal-builder') }}" data-testid="chat-build-a-meal">Build a meal</a>
      <button type="button" class="chat-close" id="chat-close" data-testid="chat-close">
        <span class="visually-hidden">Close the menu assistant</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <div class="chat-log" id="chat-log" role="log" aria-live="polite" data-testid="chat-log">
      <p class="chat-msg chat-msg-bot">Hi! Ask me about dishes, prices, dietary tags or our opening hours.</p>
    </div>

    <form class="chat-form" id="chat-form" action="{{ route('ai.chat') }}" method="post">
      @csrf
      <label class="visually-hidden" for="chat-message">Your question</label>
      <input type="text" id="chat-message" name="message" maxlength="500" autocomplete="off" required placeholder="What is vegetarian?" data-testid="chat-input">
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

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var question = input.value.trim();
    if (question === '') { return; }

    bubble(question, 'you');
    input.value = '';
    send.disabled = true;
    var pending = bubble('Thinking…', 'bot');

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
      pending.textContent = data.answer;
      chips(data.items);

      history.push({ role: 'user', content: question });
      history.push({ role: 'assistant', content: data.answer });
      history = history.slice(-10);
    })
    .catch(function (error) {
      send.disabled = false;
      pending.textContent = error.message;
    });
  });
});
</script>
@endpush
@endif
