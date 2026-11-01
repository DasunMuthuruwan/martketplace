@extends('layouts.public')

@section('title', $country->name)

@section('content')
    @php($cc = strtolower($country->iso2))

    {{-- Hero (layout doc §3.1). Phones get a search box here: the header only has a search icon. --}}
    <section class="dark relative mb-8 overflow-hidden rounded-sheet bg-ink px-5 py-9 text-fg sm:px-10 sm:py-14 lg:px-14 lg:py-16">
        <div aria-hidden="true" class="absolute -top-24 -right-16 hidden size-[26rem] rounded-full bg-primary xl:block"></div>
        <div aria-hidden="true" class="absolute right-52 -bottom-24 hidden size-56 rounded-full bg-accent xl:block"></div>
        <h1 class="max-w-3xl text-[2rem] leading-[2.25rem] font-bold tracking-tight sm:text-display lg:text-[3.5rem] lg:leading-[3.75rem]">{{ __('public.home.hero', ['country' => $country->name]) }}</h1>
        <p class="mt-3 max-w-2xl text-body text-fg-muted sm:text-lg">{{ __('public.home.subline') }}</p>
        <form method="GET" action="{{ route('ads.country', ['cc' => $cc]) }}" role="search" class="mt-6 flex max-w-2xl">
            <label for="home-q" class="sr-only">{{ __('layout.search_label') }}</label>
            <input id="home-q" type="search" name="q" maxlength="100" @include('partials.search.suggest-attributes', ['cc' => $cc]) placeholder="{{ __('layout.search_placeholder') }}"
                class="h-11 min-w-0 flex-1 rounded-l-control border border-r-0 border-control bg-surface px-3 text-fg placeholder:text-fg-subtle">
            <button type="submit" class="grid h-11 w-12 place-items-center rounded-r-control bg-primary text-primary-fg" aria-label="{{ __('layout.search') }}"><x-ui.icon name="search" size="20" /></button>
        </form>
        @if ($postUrl = \App\View\Navigation::url('post'))
            <x-ui.button variant="accent" :href="$postUrl" icon="plus" size="lg" class="mt-6 hidden lg:inline-flex">{{ __('public.home.post_cta') }}</x-ui.button>
        @endif
    </section>

    <x-banner.slot :placement="\App\Enums\BannerPlacement::HomeTop" :country="$country" />

    @if ($categories->isNotEmpty())
        <section aria-labelledby="home-categories" class="mb-10">
            <h2 id="home-categories" class="mb-3 text-h2 font-semibold">{{ __('public.home.browse') }}</h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($categories as $category)
                    <li><x-category.tile :category="$category" :count="$categoryCounts[$category->id] ?? 0" :href="route('ads.category', ['cc' => $cc, 'category' => $category->slug])" class="h-full" /></li>
                @endforeach
            </ul>
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
                                <li><a href="{{ route('ads.country', ['cc' => $cc, 'q' => $item['term']]) }}" class="inline-flex h-8 items-center rounded-full border border-line bg-surface-muted px-3 text-small text-fg hover:border-control">{{ $item['term'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($trendingModels !== [])
                    <div>
                        <h3 class="mb-2 text-small font-medium text-fg-muted">{{ __('public.trends.models') }}</h3>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($trendingModels as $item)
                                <li><a href="{{ route('ads.country', ['cc' => $cc, 'q' => $item['label']]) }}" class="inline-flex h-8 items-center rounded-full border border-line bg-surface-muted px-3 text-small text-fg hover:border-control">{{ $item['label'] }}</a></li>
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
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="home-latest" class="text-h2 font-semibold">{{ __('public.latest') }}</h2>
            @if ($latest->isNotEmpty())
                <a href="{{ route('ads.country', ['cc' => $cc]) }}" class="inline-flex items-center gap-1 text-ui text-fg-muted hover:text-fg">{{ __('public.home.see_all') }}<x-ui.icon name="chevron-right" size="16" /></a>
            @endif
        </div>
        @if ($latest->isEmpty())
            <x-ui.empty-state icon="package" :title="__('public.home.empty_title')">
                {{ __('public.home.empty_text', ['country' => $country->name]) }}
                @if ($postUrl = \App\View\Navigation::url('post'))
                    <x-slot:action><x-ui.button :href="$postUrl" icon="plus">{{ __('public.home.post_cta') }}</x-ui.button></x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($latest as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" class="h-full" :eager="$loop->first ? 'high' : $loop->index < 4" /></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @include('public.partials.safety-strip')
@endsection
