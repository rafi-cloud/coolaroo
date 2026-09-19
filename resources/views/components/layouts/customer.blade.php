@props([
    'title' => null,
    'description' => null,
    'tableLabel' => null,
])
<x-document css="style.css" suffix="Coolaroo Restaurant & Bistro" :vite="true" :title="$title" :description="$description">
@if ($tableLabel)
  <x-site.order-bar :label="$tableLabel" />
@else
  <x-site.header />
@endif

<main id="top">
  {{ $slot }}
</main>
</x-document>
