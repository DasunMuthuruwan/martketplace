@props(['variant' => 'line']) {{-- line | block | card --}}
{{-- Placeholder while content loads: hidden from assistive technology; the region sets aria-busy. --}}
@if ($variant === 'card')
    <div aria-hidden="true" {{ $attributes->class('overflow-hidden rounded-card border border-line bg-surface') }}>
        <div class="aspect-[4/3] animate-pulse bg-surface-muted motion-reduce:animate-none"></div>
        <div class="space-y-2 p-3">
            <div class="h-4 w-2/3 animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
            <div class="h-3 w-full animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
            <div class="h-3 w-1/2 animate-pulse rounded bg-surface-muted motion-reduce:animate-none"></div>
        </div>
    </div>
@else
    <div aria-hidden="true" {{ $attributes->class(['animate-pulse rounded bg-surface-muted motion-reduce:animate-none', $variant === 'block' ? 'h-24 w-full' : 'h-4 w-full']) }}></div>
@endif
