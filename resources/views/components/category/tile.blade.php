@props(['category', 'href', 'count' => null])
{{-- Category tile (components §5.3): icon, name and, when known, the number of live ads. The whole
     tile is the link. --}}
@php($icon = $category->icon && \App\Support\Icons::exists($category->icon) ? $category->icon : 'package')
<a href="{{ $href }}" {{ $attributes->class('group flex min-h-32 flex-col items-start justify-between gap-4 rounded-card border border-line bg-surface p-4 transition-[border-color,box-shadow,transform] duration-(--duration-menu) hover:-translate-y-0.5 hover:border-primary hover:shadow-card motion-reduce:transform-none') }}>
    <span class="grid size-12 place-items-center rounded-control bg-primary/10 text-primary transition-colors duration-(--duration-menu) group-hover:bg-primary group-hover:text-primary-fg"><x-ui.icon :name="$icon" size="24" /></span>
    <span class="min-w-0">
        <span class="line-clamp-2 text-ui font-semibold text-fg">{{ $category->name }}</span>
        @if ($count !== null)<span class="tabular text-small text-fg-subtle">{{ trans_choice('public.ads_count', $count, ['count' => number_format($count)]) }}</span>@endif
    </span>
</a>
