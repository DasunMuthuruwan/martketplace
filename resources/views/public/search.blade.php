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
            @if ($category)
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

    @if ($criteria->priceIgnored)
        <x-ui.alert variant="info" class="mb-4">{{ __('public.price_needs_currency') }}</x-ui.alert>
    @endif

    <div class="lg:grid lg:grid-cols-[272px_minmax(0,1fr)] lg:gap-8">
        <aside class="hidden lg:block" aria-label="{{ __('search.filters') }}">
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
