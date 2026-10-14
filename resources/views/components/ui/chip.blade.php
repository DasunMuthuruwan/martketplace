@props([
    'variant' => 'static', // static | filter (removable) | choice (toggle)
    'selected' => false,
    'href' => null,
])
@php
    $base = 'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-small transition-colors duration-(--duration-fast)';
    $tone = $selected ? 'border-primary bg-primary text-primary-fg' : 'border-line bg-surface-muted text-fg hover:border-control';
    $text = trim(strip_tags((string) $slot));
@endphp
@if ($variant === 'filter')
    {{-- The whole chip removes the filter; its name says which (component doc §1). --}}
    @if ($href !== null)
        <a href="{{ $href }}" {{ $attributes->class([$base, $tone])->merge(['aria-label' => __('ui.remove_filter', ['name' => $text])]) }}>
            {{ $slot }} <x-ui.icon name="x" size="16" />
        </a>
    @else
        <button type="button" {{ $attributes->class([$base, $tone])->merge(['aria-label' => __('ui.remove_filter', ['name' => $text])]) }}>
            {{ $slot }} <x-ui.icon name="x" size="16" />
        </button>
    @endif
@elseif ($variant === 'choice')
    <button type="button" aria-pressed="{{ $selected ? 'true' : 'false' }}" {{ $attributes->class([$base, $tone]) }}>{{ $slot }}</button>
@elseif ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class([$base, $tone]) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->class([$base, 'border-line bg-surface-muted text-fg']) }}>{{ $slot }}</span>
@endif
