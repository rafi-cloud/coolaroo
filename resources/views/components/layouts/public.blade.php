@props([
    'title' => null,
    'description' => null,
])
<x-document css="style.css" suffix="Coolaroo Restaurant & Bistro" :title="$title" :description="$description">
<a class="skip-link" href="#top" data-testid="site-skip-link">Skip to main content</a>
<x-site.header />

<main id="top">
  {{ $slot }}
</main>

<x-site.footer />

@stack('widgets')

<a class="totop" href="#top" aria-label="Back to top" data-testid="site-to-top">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 15l6-6 6 6"/></svg>
</a>

<x-chat />
</x-document>
