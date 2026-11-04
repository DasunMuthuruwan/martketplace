@props(['placement', 'country' => null])
{{-- A staff-managed banner (B10): one at a time, at random among those that match, always labelled.
     U2: the label sits above it, and the height is capped so a tall image never pushes the page
     down; the image keeps its proportions (letterboxed) and its size is reserved (no layout shift). --}}
@php($banner = app(\App\Services\Banners\Banners::class)->pick($placement, $country))
@if ($banner)
    <aside aria-label="{{ __('public.banner.label') }}" {{ $attributes->class('mb-6') }} data-banner>
        <p class="mb-1 text-micro font-medium uppercase tracking-wide text-fg-subtle">{{ __('public.banner.label') }}</p>
        @if ($banner->link_url)<a href="{{ $banner->link_url }}" rel="sponsored noopener" target="_blank" class="block rounded-card">@endif
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" width="{{ $banner->width }}" height="{{ $banner->height }}" decoding="async"
                class="h-auto max-h-[clamp(6rem,22vw,15rem)] w-full rounded-card border border-line bg-surface-muted object-contain">
        @if ($banner->link_url)</a>@endif
    </aside>
@endif
