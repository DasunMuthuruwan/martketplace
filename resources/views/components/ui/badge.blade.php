@props([
    'variant' => 'neutral', // neutral | success | warning | danger | info | outline
    'icon' => null,
])
@php
    $variants = [
        'neutral' => 'bg-surface-muted text-fg',
        'success' => 'bg-surface-muted text-success',
        'warning' => 'bg-surface-muted text-warning',
        'danger' => 'bg-surface-muted text-danger',
        'info' => 'bg-surface-muted text-info',
        'outline' => 'border border-control text-fg',
    ];
@endphp
{{-- Status is never colour alone: the text is always there (design system §3.3). --}}
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-micro font-medium uppercase tracking-wide', $variants[$variant] ?? $variants['neutral']]) }}>
    @if ($icon)<x-ui.icon :name="$icon" size="16" class="size-3.5" />@endif
    {{ $slot }}
</span>
