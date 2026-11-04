@extends('layouts.public')

@section('title', $country->name)

@section('content')
    @php
        $cc = strtolower($country->iso2);
        $postUrl = \App\View\Navigation::url('post');
        $field = 'h-12 min-w-0 rounded-full border border-control bg-surface px-4 text-ui text-fg';
    @endphp

    {{-- Hero (layout doc §3.1, U2): purpose, one search with optional category and place, Post CTA.
         The picture on the right is drawn from the country's own top-level categories. --}}
    <section aria-labelledby="home-hero" class="dark relative mb-8 overflow-hidden rounded-sheet bg-ink text-fg">
        <div class="relative grid items-center gap-10 px-5 py-9 sm:px-10 sm:py-12 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:px-14 lg:py-14">
            <div class="min-w-0">
                <p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-small font-medium text-fg-muted">
                    <x-ui.flag :code="$country->iso2" />{{ __('public.home.eyebrow', ['country' => $country->name]) }}
                </p>
                <h1 id="home-hero" class="mt-4 max-w-3xl text-[2.25rem] leading-[2.5rem] font-bold tracking-tight sm:text-display lg:text-[3.5rem] lg:leading-[3.75rem]">{{ __('public.home.hero', ['country' => $country->name]) }}</h1>
                <p class="mt-3 max-w-2xl text-body text-fg-muted sm:text-lg">{{ __('public.home.subline', ['country' => $country->name]) }}</p>

                <form method="GET" action="{{ route('ads.country', ['cc' => $cc]) }}" role="search" class="mt-7 grid gap-2 lg:max-w-2xl">
                    <div class="flex min-w-0">
                        <label for="home-q" class="sr-only">{{ __('layout.search_label') }}</label>
                        <input id="home-q" type="search" name="q" maxlength="100" @include('partials.search.suggest-attributes', ['cc' => $cc]) placeholder="{{ __('layout.search_placeholder') }}"
                            class="h-12 min-w-0 flex-1 rounded-l-full border border-r-0 border-control bg-surface pl-5 pr-3 text-fg placeholder:text-fg-subtle">
                        <button type="submit" class="inline-flex h-12 items-center gap-2 rounded-r-full bg-cobalt px-5 font-semibold text-white hover:brightness-110" aria-label="{{ __('layout.search') }}">
                            <x-ui.icon name="search" size="20" /><span class="hidden sm:inline" aria-hidden="true">{{ __('layout.search') }}</span>
                        </button>
                    </div>
                    @if ($categories->isNotEmpty() || $regions !== [])
                        <div @class(['grid gap-2', 'grid-cols-2' => $categories->isNotEmpty() && $regions !== []])>
                            @if ($categories->isNotEmpty())
                                <label class="min-w-0"><span class="sr-only">{{ __('public.home.hero_category') }}</span>
                                    <select name="category" class="{{ $field }} w-full">
                                        <option value="">{{ __('public.home.hero_all_categories') }}</option>
                                        @foreach ($categories as $category)<option value="{{ $category->slug }}">{{ $category->name }}</option>@endforeach
                                    </select>
                                </label>
                            @endif
                            @if ($regions !== [])
                                <label class="min-w-0"><span class="sr-only">{{ $country->label('region') }}</span>
                                    <select name="region" class="{{ $field }} w-full">
                                        <option value="">{{ __('public.home.hero_all_places') }}</option>
                                        @foreach ($regions as $slug => $name)<option value="{{ $slug }}">{{ $name }}</option>@endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                    @endif
                </form>

                @if ($postUrl)
                    <x-ui.button variant="accent" :href="$postUrl" icon="plus" size="lg" class="mt-6 hidden sm:inline-flex">{{ __('public.home.post_cta') }}</x-ui.button>
                @endif
            </div>

            @if ($categories->isNotEmpty())
                {{-- Decorative: the same categories are linked in "Browse categories" below. --}}
                <div aria-hidden="true" class="hidden lg:grid lg:grid-cols-3 lg:grid-rows-[repeat(3,7.5rem)] lg:gap-3">
                    @foreach ($categories->take(6) as $category)
                        @php($tileIcon = $category->icon && \App\Support\Icons::exists($category->icon) ? $category->icon : 'package')
                        <div @class([
                            'flex flex-col justify-between rounded-card p-4',
                            'col-span-2 row-span-2 bg-cobalt text-white' => $loop->index === 0,
                            'bg-accent text-accent-fg' => $loop->index === 1,
                            'border border-line bg-surface text-fg' => $loop->index > 1,
                        ])>
                            <x-ui.icon :name="$tileIcon" size="24" :class="$loop->first ? 'size-16' : 'size-8'" />
                            <span @class(['font-semibold', 'text-h2' => $loop->index === 0, 'line-clamp-2 text-small' => $loop->index > 0])>{{ $category->name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-banner.slot :placement="\App\Enums\BannerPlacement::HomeTop" :country="$country" />

    @if ($categories->isNotEmpty())
        <section aria-labelledby="home-categories" class="mb-10">
            <h2 id="home-categories" class="mb-3 text-h2 font-semibold">{{ __('public.home.browse') }}</h2>
            {{-- Phones: one swipeable row; wider screens: a grid. --}}
            <ul class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4 xl:grid-cols-7 [&::-webkit-scrollbar]:hidden">
                @foreach ($categories as $category)
                    <li class="w-36 shrink-0 snap-start sm:w-auto"><x-category.tile :category="$category" :count="$categoryCounts[$category->id] ?? 0" :href="route('ads.category', ['cc' => $cc, 'category' => $category->slug])" class="h-full" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Featured ads (U2): running Top-ad boosts of this country, labelled; hidden when there are none. --}}
    @if ($featured->isNotEmpty())
        <section aria-labelledby="home-featured" class="mb-10 rounded-sheet border border-line bg-surface-muted p-4 sm:p-5" data-featured>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4">
                <h2 id="home-featured" class="flex items-center gap-2 text-h2 font-semibold"><span class="size-2.5 rounded-full bg-accent" aria-hidden="true"></span>{{ __('public.home.featured') }}</h2>
                <p class="text-small text-fg-subtle">{{ __('public.home.featured_help') }}
                    @if (\App\Models\Page::footerLinks()->has('promotions'))<a href="{{ route('page.show', 'promotions') }}" class="text-fg-muted underline underline-offset-2">{{ __('boosts.top.learn_more') }}</a>@endif
                </p>
            </div>
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($featured as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" class="h-full" :promoted="true" :eager="$loop->index < 2" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Trending searches and makes/models (G2, ADR-027): shown only once there is enough data. --}}
    @if ($trendingSearches !== [] || $trendingModels !== [])
        <section aria-labelledby="home-trending" class="mb-10" data-trending>
            <h2 id="home-trending" class="mb-3 text-h2 font-semibold">{{ __('public.trends.trending') }}</h2>
            <div class="space-y-3">
                @if ($trendingSearches !== [])
                    <div>
                        <h3 class="mb-2 text-small font-medium text-fg-muted">{{ __('public.trends.searches') }}</h3>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($trendingSearches as $item)
                                <li><a href="{{ route('ads.country', ['cc' => $cc, 'q' => $item['term']]) }}" class="inline-flex h-9 items-center rounded-full border border-line bg-surface px-4 text-small text-fg hover:border-primary hover:text-primary">{{ $item['term'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($trendingModels !== [])
                    <div>
                        <h3 class="mb-2 text-small font-medium text-fg-muted">{{ __('public.trends.models') }}</h3>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($trendingModels as $item)
                                <li><a href="{{ route('ads.country', ['cc' => $cc, 'q' => $item['label']]) }}" class="inline-flex h-9 items-center rounded-full border border-line bg-surface px-4 text-small text-fg hover:border-primary hover:text-primary">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($popular->isNotEmpty())
        <section aria-labelledby="home-popular" class="mb-10" data-popular>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4">
                <h2 id="home-popular" class="text-h2 font-semibold">{{ __('public.trends.popular') }}</h2>
                <p class="text-small text-fg-subtle">{{ __('public.trends.popular_help') }}</p>
            </div>
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($popular as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" class="h-full" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($mostSold !== [])
        <section aria-labelledby="home-sold" class="mb-10" data-most-sold>
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4">
                <h2 id="home-sold" class="text-h2 font-semibold">{{ __('public.trends.most_sold') }}</h2>
                <p class="text-small text-fg-subtle">{{ __('public.trends.most_sold_help') }}</p>
            </div>
            <ol class="grid gap-2 sm:grid-cols-2">
                @foreach ($mostSold as $item)
                    <li class="flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-3 py-2">
                        <a href="{{ route('ads.country', ['cc' => $cc, 'q' => $item['label']]) }}" class="min-w-0 truncate text-ui text-fg hover:underline"><span class="tabular mr-2 text-fg-subtle">{{ $loop->iteration }}.</span>{{ $item['label'] }}</a>
                        <span class="shrink-0 text-small text-fg-muted">{{ trans_choice('public.trends.sold_count', $item['sold'], ['count' => $item['sold']]) }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section aria-labelledby="home-latest" class="mb-10">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
            <div>
                <h2 id="home-latest" class="text-h2 font-semibold">{{ __('public.latest') }}</h2>
                @if ($latest->isNotEmpty())<p class="text-small text-fg-subtle">{{ __('public.home.latest_help', ['country' => $country->name]) }}</p>@endif
            </div>
            @if ($latest->isNotEmpty())
                <a href="{{ route('ads.country', ['cc' => $cc]) }}" class="inline-flex items-center gap-1 text-ui font-medium text-primary hover:underline">{{ __('public.home.see_all') }}<x-ui.icon name="chevron-right" size="16" /></a>
            @endif
        </div>
        @if ($latest->isEmpty())
            <x-ui.empty-state icon="package" :title="__('public.home.empty_title')">
                {{ __('public.home.empty_text', ['country' => $country->name]) }}
                @if ($postUrl)
                    <x-slot:action><x-ui.button variant="accent" :href="$postUrl" icon="plus">{{ __('public.home.post_cta') }}</x-ui.button></x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($latest as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" class="h-full" :eager="$featured->isEmpty() ? ($loop->first ? 'high' : $loop->index < 4) : false" /></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @include('public.partials.safety-strip')
@endsection
