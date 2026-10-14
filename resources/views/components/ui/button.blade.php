@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger | link
    'size' => 'md',           // sm 32 | md 40 | lg 44
    'href' => null,
    'type' => 'button',
    'icon' => null,           // leading icon name
    'iconRight' => null,
    'label' => null,          // accessible name for icon-only buttons
    'block' => false,
    'loading' => false,
])
@php
    $iconOnly = $label !== null && $slot->isEmpty();
    $sizes = [
        'sm' => $iconOnly ? 'size-8' : 'h-8 px-3 text-small',
        'md' => $iconOnly ? 'size-10' : 'h-10 px-4 text-ui',
        'lg' => $iconOnly ? 'size-11' : 'h-11 px-5 text-body',
    ];
    $variants = [
        'primary' => 'bg-primary text-primary-fg hover:bg-primary-hover',
        'secondary' => 'border border-control bg-surface text-fg hover:bg-surface-hover',
        'ghost' => 'text-fg hover:bg-surface-hover',
        'danger' => 'bg-danger text-canvas hover:opacity-90',
        'link' => 'text-fg underline underline-offset-2 hover:text-fg-muted',
    ];
    $classes = implode(' ', array_filter([
        'inline-flex items-center justify-center gap-2 rounded-control font-medium whitespace-nowrap select-none',
        'transition-colors duration-(--duration-fast) ease-(--ease-standard)',
        'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        $variant === 'link' ? 'h-auto p-0' : ($sizes[$size] ?? $sizes['md']),
        $variants[$variant] ?? $variants['primary'],
        $block ? 'w-full' : null,
    ]));
    $iconSize = $size === 'sm' ? 16 : 20;
@endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes)->merge(array_filter(['aria-label' => $label, 'aria-disabled' => $loading ? 'true' : null])) }}>
        @if ($icon)<x-ui.icon :name="$icon" :size="$iconSize" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :size="$iconSize" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes)->merge(array_filter(['aria-label' => $label, 'aria-busy' => $loading ? 'true' : null, 'disabled' => $loading ? true : null])) }}>
        @if ($loading)
            <x-ui.icon name="loader-circle" :size="$iconSize" class="animate-spin motion-reduce:animate-none" />
        @elseif ($icon)
            <x-ui.icon :name="$icon" :size="$iconSize" />
        @endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :size="$iconSize" />@endif
    </button>
@endif
