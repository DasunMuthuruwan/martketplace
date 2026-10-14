@props([
    'label',              // button text, or the accessible name when icon-only
    'icon' => null,
    'iconOnly' => false,
    'align' => 'right',   // left | right
])
{{-- Disclosure menu built on <details> (works without JavaScript); resources/js/ui/overlays.js
     closes it on Esc, outside click or choosing an item. Items: <x-ui.dropdown-item>. --}}
<details {{ $attributes->class('group relative') }} data-dropdown>
    <summary @class([
        'inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-control text-ui font-medium text-fg hover:bg-surface-hover [&::-webkit-details-marker]:hidden',
        'w-10 justify-center' => $iconOnly,
        'px-3' => ! $iconOnly,
    ]) @if ($iconOnly) aria-label="{{ $label }}" @endif>
        @if ($icon)<x-ui.icon :name="$icon" size="20" />@endif
        @unless ($iconOnly){{ $label }}<x-ui.icon name="chevron-down" size="16" class="transition-transform group-open:rotate-180" />@endunless
    </summary>
    <div @class([
        'absolute z-40 mt-1 min-w-48 rounded-card border border-line bg-surface p-1 shadow-overlay',
        'right-0' => $align === 'right',
        'left-0' => $align !== 'right',
    ])>
        {{ $slot }}
    </div>
</details>
