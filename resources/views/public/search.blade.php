@extends('layouts.public')

@section('title', $criteria->category?->name ?? ($country?->name ?? __('public.search_all')))

@section('content')
    @php
        $total = $results->total();
        $capped = $total >= $countCap;
        $count = trans_choice('public.ads_count', $total, ['count' => number_format($capped ? $total - 1 : $total).($capped ? '+' : '')]);
        $chips = $page->chips();
        $view = $page->view();
        $cc = $country ? strtolower($country->iso2) : null;
        $category = $criteria->category;
    @endphp

    {{-- Breadcrumbs (components §3) --}}
    <nav aria-label="{{ __('search.breadcrumbs') }}" class="mb-3 text-small text-fg-subtle">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a class="hover:text-fg" href="{{ $cc ? route('country.home', ['cc' => $cc]) : route('home') }}">{{ $country?->name ?? __('layout.nav.home') }}</a></li>
            @if ($category?->parent)
                <li aria-hidden="true">›</li>
                <li><a class="hover:text-fg" href="{{ $page->url(['category' => $category->parent->slug, 'a' => null]) }}">{{ $category->parent->name }}</a></li>
            @endif
            @if ($category && $landing)
                <li aria-hidden="true">›</li>
                <li><a class="hover:text-fg" href="{{ $page->url(['a' => null]) }}">{{ $category->name }}</a></li>
                @if ($landing['model'])
                    <li aria-hidden="true">›</li>
                    <li><a class="hover:text-fg" href="{{ route('ads.make', ['cc' => $cc, 'category' => $category->parent->slug, 'subcategory' => $category->slug, 'make' => $landing['make']->value]) }}">{{ $landing['make']->label }}</a></li>
                @endif
                <li aria-hidden="true">›</li>
                <li aria-current="page" class="text-fg">{{ $landing['model']?->label ?? $landing['make']->label }}</li>
            @elseif ($category)
                <li aria-hidden="true">›</li>
                <li aria-current="page" class="text-fg">{{ $category->name }}</li>
            @endif
        </ol>
    </nav>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-h1 font-[650]">{{ $seo->title }}</h1>
            <p class="mt-0.5 text-ui text-fg-muted" role="status" aria-live="polite">{{ $count }}</p>
        </div>
    </div>

    {{-- Price guide on make/model pages (G4): asking prices of current ads, shown from enough ads. --}}
    @if ($landing && $landing['guide'])
        @php($guide = $landing['guide'])
        <section aria-labelledby="price-guide" class="mb-5 rounded-card border border-line bg-surface p-4" data-price-guide>
            <h2 id="price-guide" class="mb-2 text-h3 font-semibold">{{ __('public.make_page.guide_title', ['what' => $landing['name']]) }}</h2>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-small text-fg-muted">{{ __('public.make_page.guide_median') }}</dt>
                    <dd><x-ui.money :amount="$guide['median']" :currency="$guide['currency']" :country="$country" /></dd>
                </div>
                <div>
                    <dt class="text-small text-fg-muted">{{ __('public.make_page.guide_range') }}</dt>
                    <dd class="text-ui font-semibold text-fg"><x-ui.money :amount="$guide['low']" :currency="$guide['currency']" :country="$country" size="inherit" /> – <x-ui.money :amount="$guide['high']" :currency="$guide['currency']" :country="$country" size="inherit" /></dd>
                </div>
                <div>
                    <dt class="text-small text-fg-muted">{{ __('public.make_page.guide_based_on') }}</dt>
                    <dd class="text-ui font-semibold text-fg">{{ trans_choice('public.make_page.guide_count', $guide['count'], ['count' => number_format($guide['count'])]) }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-small text-fg-subtle">{{ __('public.make_page.guide_note') }}</p>
        </section>
    @endif

    @if ($criteria->priceIgnored)
        <x-ui.alert variant="info" class="mb-4">{{ __('public.price_needs_currency') }}</x-ui.alert>
    @endif

    <div class="lg:grid lg:grid-cols-[272px_minmax(0,1fr)] lg:gap-8">
        <aside class="hidden lg:block" aria-labelledby="filters-heading">
            {{-- h2 for the filter groups (h3) and the results, so headings do not skip a level. --}}
            <h2 id="filters-heading" class="sr-only">{{ __('search.filters') }}</h2>
            <div class="sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto pr-1">
                @include('public.partials.search-filters', ['prefix' => 'side'])
            </div>
        </aside>

        <div class="min-w-0" data-search-results>
            {{-- Toolbar: filters (phones), sort, grid / list --}}
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" icon="sliders-horizontal" class="lg:hidden" data-modal-open="search-filters">
                    {{ __('search.filters') }}@if ($chips !== [])<span class="rounded-full bg-primary px-1.5 text-micro tabular text-primary-fg">{{ count($chips) }}</span>@endif
                </x-ui.button>
                <x-ui.dropdown :label="__('search.sort_label', ['sort' => __('public.sort_options.'.$criteria->sort)])" icon="arrow-up-down" align="left">
                    @foreach (__('public.sort_options') as $value => $text)
                        @if ($value === 'newest' || $criteria->currency !== null || $country !== null)
                            <x-ui.dropdown-item :href="$page->url(['sort' => $value])" @class(['font-semibold' => $criteria->sort === $value]) :aria-current="$criteria->sort === $value ? 'true' : null">{{ $text }}</x-ui.dropdown-item>
                        @endif
                    @endforeach
                </x-ui.dropdown>
                <div class="ml-auto inline-flex rounded-control border border-line bg-surface p-0.5" role="group" aria-label="{{ __('search.view_label') }}">
                    <a href="{{ $page->url(['view' => null]) }}" aria-label="{{ __('search.view_grid') }}" @if ($view === 'grid') aria-current="true" @endif class="grid size-9 place-items-center rounded-[4px] text-fg-muted hover:text-fg aria-[current]:bg-surface-muted aria-[current]:text-fg"><x-ui.icon name="layout-grid" size="16" /></a>
                    <a href="{{ $page->url(['view' => 'list']) }}" aria-label="{{ __('search.view_list') }}" @if ($view === 'list') aria-current="true" @endif class="grid size-9 place-items-center rounded-[4px] text-fg-muted hover:text-fg aria-[current]:bg-surface-muted aria-[current]:text-fg"><x-ui.icon name="list" size="16" /></a>
                </div>
            </div>

            {{-- Active filters --}}
            @if ($chips !== [])
                <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="{{ __('search.active_filters') }}" role="group">
                    @foreach ($chips as $chip)
                        <x-ui.chip variant="filter" :href="$chip['url']">{{ $chip['label'] }}</x-ui.chip>
                    @endforeach
                    <a href="{{ $page->clearUrl() }}" class="text-small font-medium text-fg underline underline-offset-2">{{ __('search.clear_all') }}</a>
                </div>
            @endif

            @if ($trendingInCategory !== [])
                <nav aria-label="{{ __('public.trends.in_category', ['category' => $criteria->category->name]) }}" class="mb-4 flex flex-wrap items-center gap-2" data-trending-category>
                    <span class="text-small font-medium text-fg-muted">{{ __('public.trends.in_category', ['category' => $criteria->category->name]) }}</span>
                    @foreach ($trendingInCategory as $item)
                        <a href="{{ $page->url(['q' => $item['label']]) }}" class="inline-flex h-8 items-center rounded-full border border-line bg-surface-muted px-3 text-small text-fg hover:border-control">{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            {{-- Paid placements (ADR-026, D5): labelled, at most a few, matching this search; the same
                 ads also keep their usual place in the results below. --}}
            @if ($topAds->isNotEmpty())
                <section aria-labelledby="top-ads" class="mb-6 rounded-card border border-line bg-surface-muted p-3 sm:p-4" data-top-ads>
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                        <h2 id="top-ads" class="text-h3 font-semibold">{{ __('boosts.top.heading') }}</h2>
                        <p class="text-small text-fg-subtle">{{ __('boosts.top.why') }}
                            @if (\App\Models\Page::footerLinks()->has('promotions'))<a href="{{ route('page.show', 'promotions') }}" class="text-fg-muted underline underline-offset-2">{{ __('boosts.top.learn_more') }}</a>@endif
                        </p>
                    </div>
                    @if ($view === 'list')
                        <ul class="space-y-3">
                            @foreach ($topAds as $listing)
                                <li><x-listing.card :listing="$listing" :present="$present" variant="row" :promoted="true" /></li>
                            @endforeach
                        </ul>
                    @else
                        <div class="listing-grid-area">
                            <ul class="listing-grid">
                                @foreach ($topAds as $listing)
                                    <li><x-listing.card :listing="$listing" :present="$present" class="h-full" :promoted="true" /></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            <h2 class="sr-only">{{ __('search.results') }}</h2>
            @if ($results->isEmpty())
                <x-ui.empty-state icon="search" :title="__('search.empty_title')">
                    {{ $chips !== [] ? __('search.empty_filters') : __('search.empty_text') }}
                    @if ($chips !== [])
                        <x-slot:action><x-ui.button variant="secondary" :href="$page->clearUrl()">{{ __('search.clear_all') }}</x-ui.button></x-slot:action>
                    @endif
                </x-ui.empty-state>
            @elseif ($view === 'list')
                <ul class="space-y-3">
                    @foreach ($results as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" :with-code="$country === null" variant="row" :eager="$loop->first ? 'high' : $loop->index < 4" /></li>
                    @endforeach
                </ul>
            @else
                <div class="listing-grid-area">
                    <ul class="listing-grid">
                        @foreach ($results as $listing)
                            <li><x-listing.card :listing="$listing" :present="$present" :with-code="$country === null" class="h-full" :eager="$loop->first ? 'high' : $loop->index < 4" /></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-ui.pagination :paginator="$results" :max-page="config('marketplace.listings.max_page')" class="mt-8" />
        </div>
    </div>

    <x-ui.drawer id="search-filters" :title="__('search.filters')" side="left">
        @include('public.partials.search-filters', ['prefix' => 'drawer'])
    </x-ui.drawer>
@endsection
