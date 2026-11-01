@props(['category', 'href', 'count' => null])
{{-- Category tile (components §5.3): icon, name and, when known, the number of live ads. --}}
@php($icon = $category->icon && \App\Support\Icons::exists($category->icon) ? $category->icon : 'package')
<a href="{{ $href }}" {{ $attributes->class('group flex flex-col items-start gap-3 rounded-card border border-line bg-surface p-4 transition-[border-color,box-shadow] duration-(--duration-menu) hover:border-primary hover:shadow-card') }}>
    <span class="grid size-11 place-items-center rounded-control bg-surface-muted text-primary transition-colors duration-(--duration-menu) group-hover:bg-primary group-hover:text-primary-fg"><x-ui.icon :name="$icon" size="20" /></span>
    <span class="min-w-0">
        <span class="line-clamp-2 text-ui font-semibold text-fg">{{ $category->name }}</span>
        @if ($count !== null)<span class="tabular text-small text-fg-subtle">{{ trans_choice('public.ads_count', $count, ['count' => number_format($count)]) }}</span>@endif
    </span>
</a>
