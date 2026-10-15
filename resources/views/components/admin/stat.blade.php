@props([
    'label',
    'value',
    'icon' => null,
    'href' => null,        // link to the work queue, when the viewer may open it
    'tone' => 'neutral',   // neutral | warning (work waiting)
    'note' => null,        // small line under the number
])
{{-- Stat tile (components §5.4): label, number, optional note and link. --}}
<div {{ $attributes->class('flex flex-col gap-1 rounded-card border border-line bg-surface p-4') }}>
    <p class="flex items-center gap-2 text-small text-fg-muted">
        @if ($icon)<x-ui.icon :name="$icon" size="16" @class(['text-warning' => $tone === 'warning' && $value > 0]) />@endif
        {{ $label }}
    </p>
    <p class="tabular text-h1 font-semibold text-fg">{{ number_format($value) }}</p>
    @if ($note)<p class="text-small text-fg-subtle">{{ $note }}</p>@endif
    @if ($href)
        <a href="{{ $href }}" class="mt-auto inline-flex items-center gap-1 pt-1 text-small font-medium text-fg underline-offset-2 hover:underline">{{ __('admin.dashboard.open') }}<x-ui.icon name="chevron-right" size="16" /></a>
    @endif
</div>
