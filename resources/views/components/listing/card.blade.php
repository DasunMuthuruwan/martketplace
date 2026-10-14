@props([
    'listing',
    'present',            // App\Services\Listings\ListingPresenter
    'withCode' => false,  // pages that mix countries: ISO code after ambiguous symbols, country in the place
    'variant' => 'grid',  // grid | row (list view: photo left)
])
{{-- Listing card (components §5.1): 4:3 photo, full price (Q-UX-4), title (2 lines), up to three key
     facts, place · time. The whole card is one link (the title, stretched). Photo count needs
     images_count (withCount('images')) on the query. --}}
@php
    $price = $present->priceParts($listing, $withCode);
    $unit = $present->unitPrice($listing);
    $cover = $present->coverUrl($listing);
    $facts = $present->cardFacts($listing);
    $icon = $listing->category?->parent?->icon ?? $listing->category?->icon;
    $icon = $icon && \App\Support\Icons::exists($icon) ? $icon : 'package';
    $sold = $listing->status === \App\Enums\ListingStatus::Sold;
    $row = $variant === 'row';
    // Cards show the most specific place only ("Area · 2h ago"), plus the country on mixed pages.
    $place = trim(\Illuminate\Support\Str::before($present->location($listing), ','));
    if ($withCode && $listing->country) {
        $place = trim($place.($place !== '' ? ', ' : '').$listing->country->name);
    }
@endphp
<article {{ $attributes->class([
    'group relative flex overflow-hidden rounded-card border border-line bg-surface transition-colors duration-(--duration-fast) hover:border-control focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary',
    'flex-col' => ! $row,
]) }}>
    <div @class(['relative shrink-0 bg-surface-muted aspect-[4/3]', 'w-32 sm:w-40' => $row])>
        @if ($cover)
            <img src="{{ $cover }}" alt="" width="480" height="360" loading="lazy" decoding="async" class="size-full object-cover">
        @else
            <div class="grid size-full place-items-center text-fg-subtle"><x-ui.icon :name="$icon" size="24" /></div>
        @endif
        @if (($listing->images_count ?? 0) > 1)
            <span class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-overlay px-2 py-0.5 text-micro text-white">
                <x-ui.icon name="camera" size="16" class="size-3.5" />{{ $listing->images_count }}<span class="sr-only"> {{ __('public.card.photos') }}</span>
            </span>
        @endif
        @if ($sold)
            <x-ui.badge variant="neutral" class="absolute left-2 top-2">{{ __('public.card.sold') }}</x-ui.badge>
        @endif
    </div>
    <div class="flex min-w-0 flex-1 flex-col gap-1 p-3">
        <p class="flex flex-wrap items-baseline gap-x-1.5">
            <span class="tabular text-price font-bold text-fg">{{ $price['amount'] }}</span>
            @if ($price['suffix'] !== '')<span class="text-small text-fg-subtle">{{ $price['suffix'] }}</span>@endif
        </p>
        @if ($unit)<p class="tabular text-small text-fg-muted">{{ $unit }}</p>@endif
        <h3 class="line-clamp-2 text-ui text-fg">
            <a href="{{ $listing->publicUrl() }}" class="after:absolute after:inset-0 focus:outline-none">{{ $listing->title }}</a>
        </h3>
        @if ($facts !== [])<p class="line-clamp-1 text-small text-fg-muted">{{ implode(' · ', $facts) }}</p>@endif
        <p class="mt-auto line-clamp-1 pt-1 text-small text-fg-subtle">
            {{ $place }}@if ($listing->published_at)@if ($place !== '') · @endif<time datetime="{{ $listing->published_at->toIso8601String() }}">{{ $listing->published_at->diffForHumans(short: true) }}</time>@endif
        </p>
    </div>
</article>
