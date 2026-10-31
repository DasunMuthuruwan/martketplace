@props(['placement', 'country' => null])
{{-- A staff-managed banner (B10): one at a time, at random among those that match, always labelled. --}}
@php($banner = app(\App\Services\Banners\Banners::class)->pick($placement, $country))
@if ($banner)
    <aside aria-label="{{ __('public.banner.label') }}" {{ $attributes->class('mb-6') }} data-banner>
        @if ($banner->link_url)<a href="{{ $banner->link_url }}" rel="sponsored noopener" target="_blank" class="block rounded-card">@endif
            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" width="{{ $banner->width }}" height="{{ $banner->height }}" decoding="async" class="h-auto w-full rounded-card border border-line">
        @if ($banner->link_url)</a>@endif
        <p class="mt-1 text-right text-micro text-fg-subtle">{{ __('public.banner.label') }}</p>
    </aside>
@endif
