@props(['category', 'href', 'count' => null])
{{-- Category tile (components §5.3): icon, name and, when known, the number of live ads. --}}
@php($icon = $category->icon && \App\Support\Icons::exists($category->icon) ? $category->icon : 'package')
<a href="{{ $href }}" {{ $attributes->class('flex flex-col items-start gap-3 rounded-card border border-line bg-surface p-3 transition-colors duration-(--duration-fast) hover:border-control sm:p-4') }}>
    <span class="grid size-10 place-items-center rounded-control bg-surface-muted text-fg"><x-ui.icon :name="$icon" size="20" /></span>
    <span class="min-w-0">
        <span class="line-clamp-2 text-ui font-semibold text-fg">{{ $category->name }}</span>
        @if ($count !== null)<span class="tabular text-small text-fg-subtle">{{ trans_choice('public.ads_count', $count, ['count' => number_format($count)]) }}</span>@endif
    </span>
</a>
