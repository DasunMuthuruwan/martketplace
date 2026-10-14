@props([
    'variant' => 'info', // info | success | warning | danger
    'title' => null,
    'icon' => null,
])
@php
    $icons = ['info' => 'info', 'success' => 'circle-check', 'warning' => 'triangle-alert', 'danger' => 'circle-alert'];
    $tones = ['info' => 'text-info', 'success' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger'];
    $role = $variant === 'danger' ? 'alert' : 'status';
@endphp
<div role="{{ $role }}" {{ $attributes->class('flex gap-3 rounded-card border border-line bg-surface p-4 text-ui') }}>
    <x-ui.icon :name="$icon ?? ($icons[$variant] ?? 'info')" size="20" class="{{ $tones[$variant] ?? 'text-info' }} mt-0.5" />
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold text-fg">{{ $title }}</p>@endif
        <div class="text-fg-muted">{{ $slot }}</div>
        @isset($actions)<div class="mt-3 flex flex-wrap gap-2">{{ $actions }}</div>@endisset
    </div>
</div>
