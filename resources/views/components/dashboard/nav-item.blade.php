@props(['item'])
@php($active = request()->is(...$item['match']))
<a @class(['nav-item', 'is-active' => $active]) href="{{ url($item['url']) }}" data-testid="nav-{{ $item['id'] }}" @if ($active) aria-current="page" @endif>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $item['icon'] !!}</svg>
  <span>{{ $item['label'] }}</span>
</a>