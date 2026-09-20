<x-layouts.public
    title="Coolaroo Restaurant & Bistro — Taste something new"
    description="Coolaroo Restaurant & Bistro. Wood-fired pizza, burgers and fresh seafood. Scan the QR code at your table for the menu, or book ahead online."
>
  {{-- S01: Hero Section --}}
  <section class="hero" data-testid="home-hero">
    <img class="hero-img" src="{{ asset('images/hero-1.jpg') }}" alt="A table of wood-fired dishes at Coolaroo">
    <div class="hero-shade"></div>

    <div class="wrap hero-inner">
      <h1>TASTE SOMETHING NEW</h1>
      <p>Wood-fired, hand-made, and served hot since 2005</p>
      <a class="btn btn-amber" href="{{ url('/#menu') }}" data-testid="home-hero-menu">See the menu</a>
    </div>

    <div class="dots" aria-hidden="true"><span class="on"></span><span></span><span></span></div>
  </section>

  {{-- S01: Action Tiles --}}
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

  {{-- S01: About Section --}}
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

  {{-- Section Anchor Hooks for subsequent Phase 6 tasks --}}
  <div id="table-order-info" class="visually-hidden" aria-hidden="true"></div>
  <div id="menu" class="visually-hidden" aria-hidden="true"></div>
  <div id="reviews" class="visually-hidden" aria-hidden="true"></div>
  <div id="reserve" class="visually-hidden" aria-hidden="true"></div>
</x-layouts.public>
