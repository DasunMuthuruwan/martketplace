@props([
    'listing',
    'present',            // App\Services\Listings\ListingPresenter
    'withCode' => false,  // pages that mix countries: ISO code after ambiguous symbols, country in the place
    'variant' => 'grid',  // grid | row (list view: photo left)
    'eager' => false,     // first cards of a page (likely above the fold): load at once, not lazily (LCP)
    'promoted' => false,  // shown in the top-ad block: carries the visible "Top ad" label (ADR-026)
])
{{-- Listing card (components §5.1): 4:3 photo, full price (Q-UX-4), title (2 lines), up to three key
     facts, place · time. The whole card is one link (the title, stretched); the heart (U2) sits above
     it. Photo count needs images_count (withCount('images')) on the query. --}}
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
    $heart = 'absolute right-2 top-2 z-10 grid size-9 place-items-center rounded-full bg-surface/90 text-fg shadow-card transition-colors duration-(--duration-fast) hover:text-danger focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary aria-pressed:text-danger aria-pressed:[&_svg]:fill-current';
@endphp
<article {{ $attributes->class([
    'group relative flex overflow-hidden rounded-card border border-line bg-surface transition-[border-color,box-shadow] duration-(--duration-menu) hover:border-control hover:shadow-card focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary',
    'flex-col' => ! $row,
]) }}>
    <div @class(['relative shrink-0 overflow-hidden bg-surface-muted aspect-[4/3]', 'w-36 sm:w-56' => $row])>
        @if ($cover)
            <img src="{{ $cover }}" alt="" width="480" height="360" @if ($eager) fetchpriority="{{ $eager === 'high' ? 'high' : 'auto' }}" @else loading="lazy" @endif decoding="async" class="size-full object-cover transition-transform duration-(--duration-modal) ease-(--ease-standard) group-hover:scale-[1.03] motion-reduce:transform-none">
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
        @elseif ($promoted || $listing->isUrgent())
            <span class="absolute left-2 top-2 flex flex-wrap gap-1">
                @if ($promoted)<x-ui.badge variant="neutral" data-top-ad-badge>{{ __('boosts.top.badge') }}</x-ui.badge>@endif
                @if ($listing->isUrgent())<x-ui.badge variant="accent" data-urgent-badge>{{ __('boosts.urgent.badge') }}</x-ui.badge>@endif
            </span>
        @endif
        @unless ($sold)
            @auth
                @php($saved = app(\App\Services\Listings\FavouriteIds::class)->has($listing->id))
                <button type="button" data-card-favourite aria-pressed="{{ $saved ? 'true' : 'false' }}" data-favourite-url="{{ route('listings.favourite', $listing) }}"
                    data-label-on="{{ __('listings.detail.saved') }}" data-label-off="{{ __('public.save') }}" data-error="{{ __('public.card.save_error') }}"
                    aria-label="{{ __('public.card.save', ['title' => $listing->title]) }}" class="{{ $heart }}"><x-ui.icon name="heart" size="20" /></button>
            @else
                <a data-card-favourite href="{{ route('login') }}" aria-label="{{ __('public.card.save', ['title' => $listing->title]) }}" class="{{ $heart }}"><x-ui.icon name="heart" size="20" /></a>
            @endauth
        @endunless
    </div>
    <div @class(['flex min-w-0 flex-1 flex-col gap-1 p-3', 'sm:p-4' => $row])>
        <p class="flex flex-wrap items-baseline gap-x-1.5">
            <span class="tabular text-price font-bold text-fg">{{ $price['amount'] }}</span>
            @if ($price['suffix'] !== '')<span class="text-small text-fg-subtle">{{ $price['suffix'] }}</span>@endif
        </p>
        @if ($unit)<p class="tabular text-small text-fg-muted">{{ $unit }}</p>@endif
        <h3 @class(['line-clamp-2 text-ui font-medium text-fg', 'sm:text-body' => $row])>
            <a href="{{ $listing->publicUrl() }}" class="after:absolute after:inset-0 focus:outline-none">{{ $listing->title }}</a>
        </h3>
        @if ($facts !== [])<p class="line-clamp-1 text-small text-fg-muted">{{ implode(' · ', $facts) }}</p>@endif
        <p class="mt-auto flex min-w-0 items-center gap-1 pt-1 text-small text-fg-subtle">
            @if ($place !== '')<x-ui.icon name="map-pin" size="16" class="size-3.5 shrink-0" />@endif
            <span class="truncate">{{ $place }}@if ($listing->published_at)@if ($place !== '') · @endif<time datetime="{{ $listing->published_at->toIso8601String() }}">{{ $listing->published_at->diffForHumans(short: true) }}</time>@endif</span>
        </p>
    </div>
</article>
