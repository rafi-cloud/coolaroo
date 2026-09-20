@props([
    'venue' => null,
])
@php
    $venue = $venue ?? app(\App\Services\SettingService::class)->venue();
    $rawAddress = $venue['address'] ?? '';
    $addressParts = array_map('trim', explode(',', $rawAddress));
@endphp
<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div class="fcol">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M12 22s8-6.4 8-12a8 8 0 1 0-16 0c0 5.6 8 12 8 12z"/><circle cx="12" cy="10" r="3"/></svg></span>
        <div>
          <h2 class="foot-h">Address</h2>
          <p data-testid="site-footer-address">
            @if(count($addressParts) >= 2)
              {{ $addressParts[0] }}<br>{{ implode(', ', array_slice($addressParts, 1)) }}
            @else
              {{ $rawAddress }}
            @endif
          </p>
        </div>
      </div>

      <div class="fcol">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/></svg></span>
        <div>
          <h2 class="foot-h">Reservations</h2>
          <p data-testid="site-footer-reservations">
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $venue['phone'] ?? '') }}" data-testid="site-footer-phone">{{ $venue['phone'] ?? '' }}</a><br>
            <a href="mailto:{{ $venue['email'] ?? '' }}" data-testid="site-footer-email">{{ $venue['email'] ?? '' }}</a>
          </p>
        </div>
      </div>

      <div class="fcol">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <div>
          <h2 class="foot-h">Opening Hours</h2>
          <p data-testid="site-footer-hours">
            @foreach($venue['formatted_hours'] as $hourLine)
              {{ $hourLine }}@if(! $loop->last)<br>@endif
            @endforeach
          </p>
        </div>
      </div>

      <div class="fcol">
        <div class="fcol-wide">
          <h2 class="foot-h">Keep in touch</h2>
          <p class="nl-note">Specials, new dishes and the odd free dessert.</p>
          <form class="subscribe" action="#" method="get" onsubmit="return false;">
            <label class="visually-hidden" for="nl">Your email</label>
            <input id="nl" type="email" placeholder="Your email" data-testid="site-footer-subscribe-email">
            <button type="submit" aria-label="Sign up" data-testid="site-footer-subscribe-btn">&#8250;</button>
          </form>
        </div>
      </div>
    </div>

    <div class="foot-base">
      <span data-testid="site-footer-copyright">&copy; {{ now()->year }} {{ $venue['name'] ?? 'Coolaroo Restaurant' }}. All rights reserved.</span>
      <div class="foot-links">
        <a href="{{ route('privacy') }}" data-testid="site-footer-privacy-link">Privacy Policy</a>
        <span aria-hidden="true">&bull;</span>
        <a href="{{ route('terms') }}" data-testid="site-footer-terms-link">Terms &amp; Conditions</a>
      </div>
      <span class="socials">
        @php
            $fb = $venue['socials']['facebook'] ?? '';
            $x = $venue['socials']['x'] ?? '';
            $ig = $venue['socials']['instagram'] ?? '';
            $tt = $venue['socials']['tiktok'] ?? '';
            $wa = $venue['socials']['whatsapp'] ?? '';
        @endphp
        <a href="{{ $fb ?: '#' }}" aria-label="Facebook" data-testid="site-social-facebook"@if($fb) target="_blank" rel="noopener noreferrer"@endif>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M14 9V7c0-1 .3-1.5 1.6-1.5H17V2.5h-2.6C11.5 2.5 10.6 4 10.6 6.7V9H8.5v3h2.1v9.5H14V12h2.4l.4-3z"/></svg>
        </a>
        <a href="{{ $x ?: '#' }}" aria-label="X" data-testid="site-social-x"@if($x) target="_blank" rel="noopener noreferrer"@endif>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.5 3h3l-6.6 7.6L21.8 21h-6l-4.7-6.1L5.6 21h-3l7-8.1L2.6 3h6.2l4.3 5.6zm-1 16h1.7L7.6 4.7H5.8z"/></svg>
        </a>
        <a href="{{ $ig ?: '#' }}" aria-label="Instagram" data-testid="site-social-instagram"@if($ig) target="_blank" rel="noopener noreferrer"@endif>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.2" fill="currentColor" stroke="none"/></svg>
        </a>
        <a href="{{ $tt ?: '#' }}" aria-label="TikTok" data-testid="site-social-tiktok"@if($tt) target="_blank" rel="noopener noreferrer"@endif>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M14 3h3a5 5 0 0 0 4 4v3a8 8 0 0 1-4-1.2V15a6 6 0 1 1-6-6c.4 0 .7 0 1 .1v3.2A2.8 2.8 0 1 0 14 15z"/></svg>
        </a>
        <a href="{{ $wa ?: '#' }}" aria-label="WhatsApp" data-testid="site-social-whatsapp"@if($wa) target="_blank" rel="noopener noreferrer"@endif>
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-3-.2-.3A8 8 0 1 1 12 20zm4.5-5.7c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5.2-.5-.1-.5-.7-1.6c-.2-.4-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3A3 3 0 0 0 7 10a5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c2.1.8 2.1.6 2.5.5a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .1-1.2z"/></svg>
        </a>
      </span>
    </div>
  </div>
</footer>